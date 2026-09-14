import json, requests

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
key = creds["ticket_api_key"]

payload = {"uid": 4078, "date": "Sat, 12 Sep 2026 11:41:26 GMT"}
r = requests.post("https://azwebcorp.com/wp-json/azwc-tickets/v1/checkpoint",
                   headers={"X-AZWC-Key": key}, json=payload, timeout=30)
print(r.status_code)
print(r.text)
