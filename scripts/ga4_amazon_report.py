#!/usr/bin/env python3
"""
=============================================================================
         HELMETSAN GA4 AMAZON OUTBOUND CONVERSION REPORT
=============================================================================
Directly queries the Google Analytics Data API (v1beta) to analyze:
  - Outbound Amazon clicks broken down by Store Region (UK, DE, FR, US, etc.)
  - Catalog Language vs Destination Store correlation
  - Visitor Country vs Store routing accuracy
  - Link Type distribution (direct_asin vs search_query)
"""

import sys
import os
import argparse
from datetime import datetime, timedelta
import warnings

warnings.filterwarnings("ignore", category=FutureWarning)

from google.oauth2 import service_account
from googleapiclient.discovery import build

KEY_FILE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "secrets", "ash-site-502901-cd0bf333dc7c.json")
PROPERTY_ID = "properties/525320520"

def get_client():
    if not os.path.exists(KEY_FILE):
        print(f"❌ Key file not found at {KEY_FILE}")
        sys.exit(1)
    creds = service_account.Credentials.from_service_account_file(
        KEY_FILE,
        scopes=["https://www.googleapis.com/auth/analytics.readonly"]
    )
    return build("analyticsdata", "v1beta", credentials=creds, cache_discovery=False)

def run_realtime(client):
    print("\n⚡ Querying GA4 Realtime Amazon Outbound Events (Last 30 mins)...")
    try:
        res = client.properties().runRealtimeReport(
            property=PROPERTY_ID,
            body={
                "dimensions": [
                    {"name": "eventName"},
                    {"name": "country"}
                ],
                "metrics": [{"name": "eventCount"}]
            }
        ).execute()

        rows = res.get("rows", [])
        if not rows:
            print("  No realtime events in the last 30 minutes.")
            return

        print(f"\n  Found {len(rows)} realtime event clusters:")
        print(f"  {'Event Name':<30} {'Visitor Country':<20} {'Count':>10}")
        print("  " + "-" * 62)
        for r in rows:
            ev = r["dimensionValues"][0]["value"]
            country = r["dimensionValues"][1]["value"]
            cnt = r["metricValues"][0]["value"]
            print(f"  {ev:<30} {country:<20} {cnt:>10}")
    except Exception as e:
        print(f"❌ Error: {e}")

def run_amazon_report(client, days=30):
    start_date = (datetime.now() - timedelta(days=days)).strftime("%Y-%m-%d")
    end_date = datetime.now().strftime("%Y-%m-%d")

    print(f"\n📊 Querying GA4 Amazon Outbound Conversions ({start_date} to {end_date})...")
    
    try:
        res = client.properties().runReport(
            property=PROPERTY_ID,
            body={
                "dateRanges": [{"startDate": start_date, "endDate": "today"}],
                "dimensions": [
                    {"name": "customEvent:amazon_store_region"},
                    {"name": "customEvent:catalog_language"},
                    {"name": "customEvent:link_type"}
                ],
                "metrics": [
                    {"name": "eventCount"}
                ],
                "dimensionFilter": {
                    "filter": {
                        "fieldName": "eventName",
                        "stringFilter": {
                            "matchType": "EXACT",
                            "value": "amazon_outbound_click"
                        }
                    }
                }
            }
        ).execute()

        rows = res.get("rows", [])
        if not rows:
            print("\n  ℹ️ No 'amazon_outbound_click' events recorded yet for this date range.")
            print("     (Events start accumulating immediately as visitors click Amazon buttons on live site!)")
            return

        print("\n" + "=" * 75)
        print(f"  {'Store Region':<15} {'Language':<12} {'Link Type':<18} {'Clicks':>10}")
        print("=" * 75)

        total_clicks = 0
        for r in rows:
            region = r["dimensionValues"][0]["value"] or "Unknown"
            lang = r["dimensionValues"][1]["value"] or "Unknown"
            ltype = r["dimensionValues"][2]["value"] or "Unknown"
            count = int(r["metricValues"][0]["value"])
            total_clicks += count
            print(f"  {region:<15} {lang:<12} {ltype:<18} {count:>10,}")

        print("-" * 75)
        print(f"  {'TOTAL':<47} {total_clicks:>10,}")
        print("=" * 75 + "\n")

    except Exception as e:
        print(f"❌ Query Error: {e}")

def main():
    parser = argparse.ArgumentParser(description="Helmetsan GA4 Amazon Reporting Tool")
    parser.add_argument("--days", type=int, default=30, help="Days to look back (default: 30)")
    parser.add_argument("--realtime", action="store_true", help="Query live realtime events")
    args = parser.parse_args()

    client = get_client()
    if args.realtime:
        run_realtime(client)
    else:
        run_amazon_report(client, days=args.days)

if __name__ == "__main__":
    main()
