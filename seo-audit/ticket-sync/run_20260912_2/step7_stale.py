import json, requests

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
key = creds["ticket_api_key"]

r = requests.get("https://azwebcorp.com/wp-json/azwc-tickets/v1/stale?hours=48",
                  headers={"X-AZWC-Key": key}, timeout=30)
print(r.status_code)
print(r.text)
