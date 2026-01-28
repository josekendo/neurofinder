#!/usr/bin/env python3
"""
Genera puntuaciones normalizadas (0.01–1.00) para 3 métricas:
 - ICDS (MIAR) -> se obtiene desde la versión archivada 2021 (MIAR LIVE ya no muestra ICDS).
 - SJR -> por defecto desde CSV local (SCImago suele estar protegido por Cloudflare).
 - JIF -> por defecto desde CSV local (JCR requiere suscripción).

Lee: Back php/api/data/sources_reliability.json
Escribe (en la raíz): source_metrics_scores_complete.json

Uso rápido:
  python3 score_sources_metrics.py --limit 20

Con datasets locales:
  python3 score_sources_metrics.py --sjr-csv sjr_lookup.csv --jif-csv jif_lookup.csv --limit 200

Formato CSV esperado (separador coma):
  - SJR: columns: title,issn,sjr   (issn opcional pero recomendado)
  - JIF: columns: title,issn,jif   (issn opcional pero recomendado)
"""

from __future__ import annotations

import argparse
import csv
import json
import math
import os
import re
import sys
import time
from dataclasses import dataclass
from datetime import datetime, timezone
from difflib import SequenceMatcher
from pathlib import Path
from typing import Any, Iterable


def _now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def _clamp(x: float, lo: float, hi: float) -> float:
    return lo if x < lo else hi if x > hi else x


def _norm_001_100(x01: float) -> float:
    """Clampa y redondea a 2 decimales dentro de 0.01..1.00."""
    return round(_clamp(x01, 0.01, 1.0), 2)


def _similarity(a: str, b: str) -> float:
    a = _normalize_title(a)
    b = _normalize_title(b)
    if not a or not b:
        return 0.0
    return SequenceMatcher(None, a, b).ratio()


def _normalize_title(s: str) -> str:
    s = (s or "").strip().lower()
    s = re.sub(r"\s+", " ", s)
    # quitar puntuación ligera
    s = re.sub(r"[\"'`´’“”.,;:!?()\\[\\]{}]", "", s)
    return s


def _normalize_issn(s: str) -> str:
    s = (s or "").strip().upper()
    s = s.replace(" ", "").replace("-", "")
    if len(s) == 8:
        return s[:4] + "-" + s[4:]
    return s


@dataclass(frozen=True)
class LookupRow:
    title: str
    issn: str | None
    value: float


def _load_lookup_csv(path: Path, value_col: str) -> list[LookupRow]:
    rows: list[LookupRow] = []
    if not path.exists():
        return rows
    with path.open("r", encoding="utf-8-sig", newline="") as f:
        text = f.read()

    # Normalizar líneas y localizar la fila de cabecera real
    raw_lines = text.splitlines()
    lines = [ln for ln in raw_lines if ln.strip() != ""]
    if not lines:
        return rows

    header_idx = 0
    for i, ln in enumerate(lines):
        if ("Title" in ln or "title" in ln or "Journal name" in ln):
            header_idx = i
            break
    useful_lines = lines[header_idx:]

    # Detectar delimitador en un pequeño sample
    sample = "\n".join(useful_lines[:10])
    try:
        dialect = csv.Sniffer().sniff(sample, delimiters=",;")
        delimiter = dialect.delimiter
    except Exception:
        delimiter = ","

    import io

    reader = csv.DictReader(io.StringIO("\n".join(useful_lines)), delimiter=delimiter)
    for r in reader:
        # Mapear nombres de columnas posibles para título / ISSN
        title = (r.get("title") or r.get("Title") or r.get("Journal name") or "").strip()
        issn_raw = (r.get("issn") or r.get("Issn") or r.get("ISSN") or "").strip()

        # Localizar columna de valor según value_col y alias conocidos
        raw = (r.get(value_col) or r.get(value_col.upper()) or "").strip()
        if not raw:
            # Heurística: buscar primera columna cuyo nombre contenga 'sjr' o 'jif'
            key_lower = value_col.lower()
            candidate_key = None
            if "sjr" in key_lower:
                for k in r.keys():
                    if k and "sjr" in k.lower():
                        candidate_key = k
                        break
            elif "jif" in key_lower:
                for k in r.keys():
                    if k and "jif" in k.lower():
                        candidate_key = k
                        break
            if candidate_key:
                raw = (r.get(candidate_key) or "").strip()

        if not title or not raw:
            continue

        # En el CSV de SCImago, ISSN puede venir como "15424863, 00079235" -> usar el primero
        issn = None
        if issn_raw:
            parts = [p.strip() for p in issn_raw.split(",") if p.strip()]
            issn = parts[0] if parts else None

        try:
            value = float(raw.replace(",", "."))
        except ValueError:
            continue

        rows.append(
            LookupRow(
                title=title,
                issn=_normalize_issn(issn) if issn else None,
                value=value,
            )
        )
    return rows


def _lookup_metric(
    *,
    query_title: str,
    query_issn: str | None,
    rows: list[LookupRow],
    similarity_threshold: float = 0.86,
) -> tuple[float | None, dict[str, Any]]:
    """
    Devuelve (value, meta) buscando primero por ISSN (si está), y si no, por fuzzy title.
    """
    meta: dict[str, Any] = {
        "matchedBy": None,
        "matchedTitle": None,
        "matchedIssn": None,
        "similarity": None,
        "candidates": 0,
    }
    if not rows:
        return None, meta

    meta["candidates"] = len(rows)

    if query_issn:
        q = _normalize_issn(query_issn)
        for r in rows:
            if r.issn and _normalize_issn(r.issn) == q:
                meta.update(
                    matchedBy="issn",
                    matchedTitle=r.title,
                    matchedIssn=r.issn,
                    similarity=1.0,
                )
                return r.value, meta

    best: LookupRow | None = None
    best_sim = 0.0
    for r in rows:
        sim = _similarity(query_title, r.title)
        if sim > best_sim:
            best_sim = sim
            best = r

    if best and best_sim >= similarity_threshold:
        meta.update(
            matchedBy="title",
            matchedTitle=best.title,
            matchedIssn=best.issn,
            similarity=best_sim,
        )
        return best.value, meta

    meta["similarity"] = best_sim if best else None
    return None, meta


def _robust_minmax_params(values: list[float]) -> tuple[float, float] | None:
    """
    Devuelve (p5, p95) para escalar de forma robusta.
    Si hay pocos datos o p5==p95, devuelve None.
    """
    if len(values) < 20:
        return None
    xs = sorted(values)
    p5 = xs[int(0.05 * (len(xs) - 1))]
    p95 = xs[int(0.95 * (len(xs) - 1))]
    if p95 <= p5:
        return None
    return p5, p95


def _score_from_value(
    value: float,
    *,
    kind: str,
    robust_params: tuple[float, float] | None = None,
) -> float:
    """
    Normaliza a 0.01..1.00.
    - ICDS: value/11 (ICDS 2021 típicamente 0..11)
    - SJR/JIF: si hay robust_params => minmax robusto p5..p95; si no => log-scaling con cap.
    """
    if kind == "icds":
        return _norm_001_100(value / 11.0)

    if robust_params is not None:
        lo, hi = robust_params
        x01 = 0.01 + 0.99 * _clamp((value - lo) / (hi - lo), 0.0, 1.0)
        return _norm_001_100(x01)

    # fallback sin dataset: log scaling con caps razonables
    if kind == "sjr":
        cap = 10.0
    elif kind == "jif":
        cap = 50.0
    else:
        cap = max(1.0, value)
    x01 = math.log10(1.0 + _clamp(value, 0.0, cap)) / math.log10(1.0 + cap)
    return _norm_001_100(x01)


def _http_get(url: str, *, timeout_s: int = 20) -> str:
    # Usamos requests + certifi para evitar problemas de CA bundle en algunos entornos.
    try:
        import requests  # type: ignore
    except Exception as e:  # pragma: no cover
        raise RuntimeError("Falta dependencia 'requests'. Instala: pip install requests certifi beautifulsoup4") from e

    try:
        import certifi  # type: ignore
        verify = certifi.where()
    except Exception:
        verify = True

    r = requests.get(
        url,
        headers={"User-Agent": "Mozilla/5.0 (compatible; NeuroFinderMetricsBot/1.0)"},
        timeout=timeout_s,
        verify=verify,
    )
    r.raise_for_status()
    return r.text


def _http_post(url: str, data: dict[str, str], *, timeout_s: int = 20) -> str:
    try:
        import requests  # type: ignore
    except Exception as e:  # pragma: no cover
        raise RuntimeError("Falta dependencia 'requests'. Instala: pip install requests certifi beautifulsoup4") from e

    try:
        import certifi  # type: ignore
        verify = certifi.where()
    except Exception:
        verify = True

    r = requests.post(
        url,
        data=data,
        headers={"User-Agent": "Mozilla/5.0 (compatible; NeuroFinderMetricsBot/1.0)"},
        timeout=timeout_s,
        verify=verify,
    )
    r.raise_for_status()
    return r.text


def _miar_search_best_match(title: str, *, max_rows: int = 25) -> tuple[str | None, str | None, str | None, float | None]:
    """
    Busca en MIAR por título y devuelve:
      (issn, matched_title, url_lista, similarity)
    """
    html = _http_post("https://miar.ub.edu/lista", data={"texto": title, "campo": "TITOL"})
    url_lista = "https://miar.ub.edu/lista (POST campo=TITOL)"

    try:
        from bs4 import BeautifulSoup  # type: ignore
    except Exception as e:  # pragma: no cover
        raise RuntimeError("Falta dependencia 'beautifulsoup4'. Instala: pip install beautifulsoup4") from e

    soup = BeautifulSoup(html, "html.parser")
    # Tabla de resultados: rows con td.issn y td.TITLE
    candidates: list[tuple[str, str, float]] = []
    for tr in soup.select("tbody tr")[:max_rows]:
        issn_a = tr.select_one("td.issn a")
        title_td = tr.select_one("td.TITLE")
        if not issn_a or not title_td:
            continue
        issn = issn_a.get_text(strip=True)
        cand_title = title_td.get_text(strip=True)
        sim = _similarity(title, cand_title)
        candidates.append((_normalize_issn(issn), cand_title, sim))

    if not candidates:
        return None, None, url_lista, None

    candidates.sort(key=lambda t: t[2], reverse=True)
    best_issn, best_title, best_sim = candidates[0]
    # Umbral algo permisivo: muchas fuentes del JSON están abreviadas ("n engl j med")
    if best_sim < 0.65:
        return None, None, url_lista, best_sim
    return best_issn, best_title, url_lista, best_sim


def _miar_get_icds_2021(issn: str) -> tuple[float | None, str]:
    issn = _normalize_issn(issn)
    url = f"https://miar.ub.edu/2021/issn/{issn}"
    # Algunas revistas pueden existir en NLM pero no en MIAR 2021 (404).
    # En ese caso devolvemos None sin abortar el proceso global.
    try:
        html = _http_get(url)
    except Exception:
        return None, url

    # Nota: en las páginas MIAR 2021 el valor suele aparecer como:
    # <div id="sp_icds">11.00</div>
    m = re.search(r'<div id="sp_icds">\s*([0-9]+(?:\.[0-9]+)?)\s*</div>', html)
    if not m:
        return None, url
    try:
        return float(m.group(1)), url
    except ValueError:
        return None, url


def _nlm_resolve_issn(title: str) -> tuple[str | None, str | None, dict[str, str]]:
    """
    Resuelve (aprox.) una abreviatura/título de revista a ISSN usando NLM Catalog (NCBI E-utilities).
    Devuelve: (issn, canonical_title, urls_usadas)
    """
    try:
        import requests  # type: ignore
        import certifi  # type: ignore
    except Exception as e:  # pragma: no cover
        raise RuntimeError("Faltan dependencias 'requests/certifi'. Instala: pip install requests certifi") from e

    verify = certifi.where()
    base = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils"

    def esearch(term: str) -> list[str]:
        r = requests.get(
            f"{base}/esearch.fcgi",
            params={"db": "nlmcatalog", "term": term, "retmode": "json", "retmax": 5},
            timeout=20,
            verify=verify,
        )
        r.raise_for_status()
        data = r.json()
        return list((data.get("esearchresult") or {}).get("idlist") or [])

    # Intentar primero como abreviatura (ta), luego como journal title (jour)
    tried_terms = [
        f"{title}[ta]",
        f"{title}[jour]",
        title,
    ]

    ids: list[str] = []
    used_search_term = None
    for term in tried_terms:
        ids = esearch(term)
        if ids:
            used_search_term = term
            break

    urls_used: dict[str, str] = {}
    if used_search_term:
        # URL explicativa (con params ya “resueltos”)
        urls_used["nlm_esearch"] = f"{base}/esearch.fcgi?db=nlmcatalog&retmode=json&retmax=5&term={requests.utils.quote(used_search_term)}"
    if not ids:
        return None, None, urls_used

    best = None
    best_sim = 0.0

    for uid in ids:
        r = requests.get(
            f"{base}/esummary.fcgi",
            params={"db": "nlmcatalog", "id": uid, "retmode": "json"},
            timeout=20,
            verify=verify,
        )
        r.raise_for_status()
        data = r.json()
        rec = (data.get("result") or {}).get(uid) or {}
        # Campos útiles
        title_main = None
        tml = rec.get("titlemainlist") or []
        if isinstance(tml, list) and tml:
            title_main = (tml[0] or {}).get("title")
        isoabbr = rec.get("isoabbreviation")
        medlineta = rec.get("medlineta")

        sim = max(_similarity(title, str(isoabbr or "")), _similarity(title, str(medlineta or "")))
        sim = max(sim, _similarity(title, str(title_main or "")))

        # ISSN: preferir "Print" si está
        issn_list = rec.get("issnlist") or []
        issns: list[tuple[str, str]] = []
        if isinstance(issn_list, list):
            for it in issn_list:
                if not isinstance(it, dict):
                    continue
                issn = it.get("issn")
                typ = it.get("issntype") or ""
                valid = (it.get("validyn") or "Y").upper()
                if isinstance(issn, str) and valid == "Y":
                    issns.append((_normalize_issn(issn), str(typ)))

        if not issns:
            continue

        # elegir mejor issn
        issns.sort(key=lambda t: (0 if "Print" in t[1] else 1, t[0]))
        picked_issn = issns[0][0]

        if sim > best_sim:
            best_sim = sim
            best = (picked_issn, title_main or isoabbr or medlineta or None, uid)

    if not best:
        return None, None, urls_used

    issn, canonical_title, uid = best
    urls_used["nlm_esummary"] = f"{base}/esummary.fcgi?db=nlmcatalog&retmode=json&id={uid}"
    return issn, (str(canonical_title) if canonical_title else None), urls_used


def main() -> int:
    repo_root = Path(__file__).resolve().parent
    default_json = repo_root / "Back php" / "api" / "data" / "sources_reliability.json"
    default_out = repo_root / "source_metrics_scores_complete.json"

    ap = argparse.ArgumentParser()
    ap.add_argument("--input", default=str(default_json), help="Ruta al JSON de fuentes (por defecto, el del backend PHP).")
    ap.add_argument("--out", default=str(default_out), help="Ruta del log de salida en la raíz.")
    ap.add_argument("--sjr-csv", default="", help="CSV local con SJR (columns: title,issn,sjr).")
    ap.add_argument("--jif-csv", default="", help="CSV local con JIF (columns: title,issn,jif).")
    ap.add_argument("--limit", type=int, default=0, help="Procesar solo N fuentes (0 = todas).")
    ap.add_argument("--sleep", type=float, default=0.35, help="Pausa entre peticiones a MIAR (segundos).")
    ap.add_argument("--min-title-sim", type=float, default=0.65, help="Umbral mínimo de similitud de título para aceptar match MIAR.")
    args = ap.parse_args()

    input_path = Path(args.input).expanduser().resolve()
    out_path = Path(args.out).expanduser().resolve()
    sjr_csv = Path(args.sjr_csv).expanduser().resolve() if args.sjr_csv else None
    jif_csv = Path(args.jif_csv).expanduser().resolve() if args.jif_csv else None

    if not input_path.exists():
        print(f"[ERROR] No existe el input: {input_path}", file=sys.stderr)
        return 2

    sources: dict[str, Any] = json.loads(input_path.read_text(encoding="utf-8"))
    items = list(sources.items())
    if args.limit and args.limit > 0:
        items = items[: args.limit]

    sjr_rows = _load_lookup_csv(sjr_csv, "sjr") if sjr_csv else []
    jif_rows = _load_lookup_csv(jif_csv, "jif") if jif_csv else []

    sjr_params = _robust_minmax_params([r.value for r in sjr_rows])
    jif_params = _robust_minmax_params([r.value for r in jif_rows])

    complete: list[dict[str, Any]] = []
    all_items: list[dict[str, Any]] = []
    stats = {
        "total": len(items),
        "miarMatched": 0,
        "nlmResolved": 0,
        "icdsFound": 0,
        "sjrFound": 0,
        "jifFound": 0,
        "complete3": 0,
    }

    for idx, (normalized_key, data) in enumerate(items, start=1):
        original = (data.get("original") if isinstance(data, dict) else None) or normalized_key
        original = str(original)

        print(f"[{idx}/{len(items)}] {original}")

        item_out: dict[str, Any] = {
            "normalizedKey": normalized_key,
            "original": original,
            "matched": {
                "issn": None,
                "miarTitle": None,
                "titleSimilarity": None,
                "matchUrls": {},
            },
            "scores": {
                "icds": {"raw": None, "score": None, "url": None},
                "sjr": {"raw": None, "score": None, "url": None, "meta": None},
                "jif": {"raw": None, "score": None, "url": None, "meta": None},
            },
            "finalScore": {"score": None, "metric": None},
            "generatedAt": _now_iso(),
        }

        # 1) MIAR match -> ISSN
        issn = None
        miar_title = None
        title_sim = None
        match_urls: dict[str, str] = {}

        try:
            issn, miar_title, miar_search_url, title_sim = _miar_search_best_match(original)
            match_urls["miar_search"] = miar_search_url
        except Exception as e:
            print(f"  - MIAR search ERROR: {e}")

        # Fallback: resolver ISSN via NLM Catalog (abreviaturas PubMed)
        if issn is None or (title_sim is not None and title_sim < args.min_title_sim):
            try:
                nlm_issn, nlm_title, nlm_urls = _nlm_resolve_issn(original)
            except Exception as e:
                nlm_issn, nlm_title, nlm_urls = None, None, {}
                print(f"  - NLM resolve ERROR: {e}")

            if nlm_issn:
                issn = nlm_issn
                miar_title = nlm_title or miar_title or original
                title_sim = 1.0
                match_urls.update(nlm_urls)
                stats["nlmResolved"] += 1
                print(f"  - NLM ISSN: {miar_title} [{issn}]")
            else:
                print(f"  - MIAR match: NO (sim={title_sim})")
                time.sleep(args.sleep)
                # registrar aunque no haya match
                item_out["matched"]["issn"] = None
                item_out["matched"]["miarTitle"] = None
                item_out["matched"]["titleSimilarity"] = title_sim
                item_out["matched"]["matchUrls"] = match_urls
                all_items.append(item_out)
                continue

        stats["miarMatched"] += 1
        print(f"  - Match final: {miar_title} [{issn}]")
        item_out["matched"]["issn"] = issn
        item_out["matched"]["miarTitle"] = miar_title
        item_out["matched"]["titleSimilarity"] = title_sim
        item_out["matched"]["matchUrls"] = match_urls

        # 2) ICDS (MIAR 2021)
        icds_val, icds_url = _miar_get_icds_2021(issn)
        item_out["scores"]["icds"]["url"] = icds_url
        icds_score = None
        if icds_val is None:
            print("  - ICDS: NO")
        else:
            stats["icdsFound"] += 1
            icds_score = _score_from_value(icds_val, kind="icds")
            print(f"  - ICDS: {icds_val} -> {icds_score}")
            item_out["scores"]["icds"]["raw"] = icds_val
            item_out["scores"]["icds"]["score"] = icds_score

        # 3) SJR (CSV local por defecto)
        sjr_val, sjr_meta = _lookup_metric(query_title=miar_title or original, query_issn=issn, rows=sjr_rows)
        sjr_url = f"file:{sjr_csv}" if sjr_csv else None
        sjr_score = _score_from_value(sjr_val, kind="sjr", robust_params=sjr_params) if sjr_val is not None else None
        item_out["scores"]["sjr"]["raw"] = sjr_val
        item_out["scores"]["sjr"]["score"] = sjr_score
        item_out["scores"]["sjr"]["url"] = sjr_url
        item_out["scores"]["sjr"]["meta"] = sjr_meta
        if sjr_val is not None:
            stats["sjrFound"] += 1
            print(f"  - SJR: {sjr_val} -> {sjr_score} (by {sjr_meta.get('matchedBy')}, sim={sjr_meta.get('similarity')})")
        else:
            print("  - SJR: NO (añade --sjr-csv)")

        # 4) JIF (CSV local por defecto)
        jif_val, jif_meta = _lookup_metric(query_title=miar_title or original, query_issn=issn, rows=jif_rows)
        jif_url = f"file:{jif_csv}" if jif_csv else None
        jif_score = _score_from_value(jif_val, kind="jif", robust_params=jif_params) if jif_val is not None else None
        item_out["scores"]["jif"]["raw"] = jif_val
        item_out["scores"]["jif"]["score"] = jif_score
        item_out["scores"]["jif"]["url"] = jif_url
        item_out["scores"]["jif"]["meta"] = jif_meta
        if jif_val is not None:
            stats["jifFound"] += 1
            print(f"  - JIF: {jif_val} -> {jif_score} (by {jif_meta.get('matchedBy')}, sim={jif_meta.get('similarity')})")
        else:
            print("  - JIF: NO (añade --jif-csv)")

        # 5) Score final según tu regla:
        #    - Si existen SJR y JIF -> score = media (50% cada una)
        #    - Si solo existe una de ellas -> usar la que exista
        #    - Si no hay SJR/JIF pero sí ICDS -> usar ICDS
        final_metric = None
        final_score = None

        if sjr_score is not None or jif_score is not None:
            if sjr_score is not None and jif_score is not None:
                # Media simple de dos scores ya en 0.01..1.00
                final_score = _norm_001_100((sjr_score + jif_score) / 2.0)
                final_metric = "sjr+jif"
            elif sjr_score is not None:
                # Solo SJR -> usar la mitad de su valor normalizado
                final_score = _norm_001_100(sjr_score / 2.0)
                final_metric = "sjr"
            else:
                # Solo JIF -> usar la mitad de su valor normalizado
                final_score = _norm_001_100(jif_score / 2.0)
                final_metric = "jif"
        elif icds_score is not None:
            final_score = icds_score
            final_metric = "icds"

        item_out["finalScore"]["metric"] = final_metric
        item_out["finalScore"]["score"] = final_score

        # Guardar en el JSON de entrada (mínimo si encuentra 1 score final)
        # Nota: solo actualizamos entradas con el formato esperado (dict).
        if isinstance(sources.get(normalized_key), dict):
            sources[normalized_key]["score"] = final_score

        # Siempre registramos el item (aunque falten SJR/JIF)
        all_items.append(item_out)

        if final_score is not None:
            # "complete3" significa "tiene score final" (al menos 1 métrica encontrada)
            stats["complete3"] += 1
            complete.append(
                {
                    "normalizedKey": normalized_key,
                    "original": original,
                    "matched": {
                        "issn": issn,
                        "miarTitle": miar_title,
                        "titleSimilarity": title_sim,
                        "matchUrls": match_urls,
                    },
                    "scores": {
                        "icds": {"raw": icds_val, "score": icds_score, "url": icds_url},
                        "sjr": {"raw": sjr_val, "score": sjr_score, "url": sjr_url, "meta": sjr_meta},
                        "jif": {"raw": jif_val, "score": jif_score, "url": jif_url, "meta": jif_meta},
                    },
                    "finalScore": {"metric": final_metric, "score": final_score},
                    "generatedAt": _now_iso(),
                }
            )

        time.sleep(args.sleep)

    out = {
        "generatedAt": _now_iso(),
        "input": str(input_path),
        "notes": {
            "icdsSource": "MIAR archived 2021 (MIAR LIVE no muestra ICDS desde 2022)",
            "sjrSource": "CSV local (SCImago suele bloquear scraping automático por Cloudflare)",
            "jifSource": "CSV local exportado desde JCR (requiere suscripción)",
        },
        "stats": stats,
        "items": all_items,
        "complete": complete,
    }

    out_path.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")
    # Persistir actualización de scores en el JSON de fuentes
    # (manteniendo el formato del backend PHP: indent=4)
    input_path.write_text(json.dumps(sources, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
    print(f"\n[OK] Log escrito: {out_path}")
    print(f"[OK] Con score final (>=1 métrica): {stats['complete3']}/{stats['total']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

