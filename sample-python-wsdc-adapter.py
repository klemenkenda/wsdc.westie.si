import requests
import json
from bs4 import BeautifulSoup
from dt import LookupResult  # Make sure dt.py defines LookupResult and related dataclasses
from dataclasses import asdict

class WSDCFetcher:
    BASE_URL = 'https://points.worldsdc.com'
    LOOKUP_PATH = '/lookup2020'
    FIND_PATH = '/lookup2020/find'

    def __init__(self):
        self.url = self.BASE_URL + self.LOOKUP_PATH
        self.find_url = self.BASE_URL + self.FIND_PATH
        self.token = None

    def get_token(self):
        if self.token is None:
            html = self._get_html(self.url)
            self.token = self._parse_html(html)
        return self.token

    def _get_html(self, url):
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'}
        response = requests.get(url, headers=headers)
        return response.text

    def _parse_html(self, html):
        soup = BeautifulSoup(html, 'html.parser')
        token_input = soup.find('input', {'name': '_token'})
        if token_input and 'value' in token_input.attrs:
            return token_input['value']
        return None

    def _get_data(self, num):
        token = self.get_token()
        payload = {
            'num': num,
            '_token': token
        }
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'
        }
        response = requests.post(self.find_url, data=payload, headers=headers)
        # print(f"Response: {response.text}")
        return response.text

    def get_structured_data(self, num: int) -> LookupResult:
        raw_json = self._get_data(num)
        data = json.loads(raw_json)
        return LookupResult.from_dict(data)  # Implement from_dict in your dt.py dataclasses

    def extract_summary(self, record: LookupResult) -> dict:
        divisions = ["CHA", "ALS", "ADV", "INT", "NOV", "NEW"]
        record_dict = asdict(record) if not isinstance(record, dict) else record

        def extract_role_flat(role_key):
            if role_key not in record_dict or not record_dict[role_key]:
                return None
            role_data = record_dict[role_key]
            if not isinstance(role_data, dict):
                return None
            placements = role_data.get("placements", {})
            if not isinstance(placements, dict):
                placements = {}
            placements = placements.get("West Coast Swing", {})
            total_points = {div: 0 for div in divisions}
            for div in divisions:
                if div in placements:
                    total_points[div] = placements[div].get("total_points", 0)
            dancer = role_data.get("dancer", {})
            wscid = dancer.get("wscid")
            first_name = dancer.get("first_name")
            last_name = dancer.get("last_name")
            all_comps = []
            comp_levels = []
            for div in placements:
                comps = placements[div].get("competitions", [])
                for comp in comps:
                    all_comps.append(comp)
                    comp_levels.append((comp, div))
            # If there are no placements or competitions, return basic info with zeros and empty competitions
            if not placements or not all_comps:
                return {
                    "wscid": wscid,
                    "first_name": first_name,
                    "last_name": last_name,
                    **total_points,
                    "first_competition": {},
                    "best_competition": {}
                }
            # First competition by earliest date string (lexical order)
            first_comp, first_level = min(comp_levels, key=lambda x: x[0]["event"]["date"])
            # Best competition: highest division (CHA>ALS>ADV>INT>NOV>NEW), then most points
            div_order = {d: i for i, d in enumerate(divisions)}
            def comp_key(x):
                comp, div = x
                return (div_order.get(div, 99), -comp["points"])
            best_comp, best_level = min(comp_levels, key=comp_key)
            def comp_info(comp, level):
                if not comp:
                    return {}
                return {
                    "event_name": comp["event"]["name"],
                    "event_location": comp["event"]["location"],
                    "event_date": comp["event"]["date"],
                    "points": comp["points"],
                    "result": comp["result"],
                    "level": level
                }
            return {
                "wscid": wscid,
                "first_name": first_name,
                "last_name": last_name,
                **total_points,
                "first_competition": comp_info(first_comp, first_level),
                "best_competition": comp_info(best_comp, best_level)
            }

        return {
            "leader": extract_role_flat("leader"),
            "follower": extract_role_flat("follower")
        }