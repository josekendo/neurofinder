import argparse
import requests
from agentql import Agent, Query


def scrape(url, endpoint, deployment, api_key):
    # Configurar AgentQL con Azure OpenAI
    client_config = {
        "api_type": "azure",
        "api_key": api_key,
        "azure_endpoint": endpoint,
        "azure_deployment": deployment
    }

    agent = Agent(
        model=deployment,
        api_key=api_key,
        client_config=client_config
    )

    # Descargar HTML (para páginas estáticas)
    html = requests.get(url, timeout=15).text

    # Query de extracción
    query = Query("""
    {
      title: Text,
      date: Text,
      content: Text
    }
    """)

    # Extraer
    result = agent.extract(html, query)
    return result


def main():
    parser = argparse.ArgumentParser(description="Scrapear noticias con AgentQL + Azure OpenAI")
    parser.add_argument("--url", required=True, help="URL de la noticia")
    parser.add_argument("--endpoint", required=True, help="Endpoint de Azure OpenAI")
    parser.add_argument("--deployment", required=True, help="Nombre del deployment")
    parser.add_argument("--key", required=True, help="API Key de Azure OpenAI")

    args = parser.parse_args()

    result = scrape(
        url=args.url,
        endpoint=args.endpoint,
        deployment=args.deployment,
        api_key=args.key
    )

    print("\n===== RESULTADO =====\n")
    print(result)


if __name__ == "__main__":
    main()
