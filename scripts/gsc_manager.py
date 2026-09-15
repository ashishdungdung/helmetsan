#!/usr/bin/env python3
"""
Helmetsan Google Search Console Manager
Enables direct automated query, sitemap submission, and URL inspection via the official GSC API.
"""

import sys
import os
import argparse
import json
import warnings

warnings.filterwarnings("ignore", category=FutureWarning)

from google.oauth2 import service_account
from googleapiclient.discovery import build

SCOPES = ["https://www.googleapis.com/auth/webmasters"]

def find_key_file():
    root_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    candidate_dirs = [
        os.path.join(root_dir, "secrets"),
        os.path.join(root_dir, "helmetsan-core", "keys"),
        root_dir
    ]
    for cdir in candidate_dirs:
        if not os.path.exists(cdir):
            continue
        for f in os.listdir(cdir):
            if f.endswith(".json") and ("ash-" in f or "service-account" in f or "gsc" in f or "google" in f):
                full_path = os.path.join(cdir, f)
                try:
                    with open(full_path, "r") as fp:
                        data = json.load(fp)
                        if data.get("type") == "service_account":
                            return full_path
                except Exception:
                    continue
    return os.path.join(root_dir, "secrets", "ash-site-502901-cd0bf333dc7c.json")

def get_service():
    key_file = find_key_file()
    if not os.path.exists(key_file):
        print(f"❌ Service account JSON key file not found in project root.")
        print(f"   Please download the JSON key for your service account and save it to the project root.")
        sys.exit(1)
    
    print(f"🔑 Using Service Account Key: {os.path.basename(key_file)}")
    creds = service_account.Credentials.from_service_account_file(key_file, scopes=SCOPES)
    return build("searchconsole", "v1", credentials=creds, cache_discovery=False), build("webmasters", "v3", credentials=creds, cache_discovery=False)

def list_sites(webmasters_service):
    print("🔍 Fetching verified sites in Search Console...")
    try:
        sites = webmasters_service.sites().list().execute()
        entries = sites.get("siteEntry", [])
        if not entries:
            print("⚠️ No sites found. Please ensure helmetsan-gsc-reader@ash-server-ind.iam.gserviceaccount.com is added as a user in GSC.")
            return []
        
        print("\n✅ Verified Sites:")
        for s in entries:
            print(f"  • {s['siteUrl']} (Permission: {s.get('permissionLevel')})")
        return [s['siteUrl'] for s in entries]
    except Exception as e:
        print(f"❌ Error listing sites: {e}")
        return []

def list_sitemaps(webmasters_service, site_url):
    print(f"\n🗺️ Fetching sitemaps for: {site_url}...")
    try:
        res = webmasters_service.sitemaps().list(siteUrl=site_url).execute()
        sitemaps = res.get("sitemap", [])
        if not sitemaps:
            print("  (No sitemaps submitted yet)")
            return
        
        print("\nFound Sitemaps:")
        for sm in sitemaps:
            path = sm.get("path")
            last_sub = sm.get("lastSubmitted")
            last_dl = sm.get("lastDownloaded")
            has_errors = sm.get("errors", 0)
            print(f"  • {path}")
            print(f"    - Last Submitted:  {last_sub}")
            print(f"    - Last Downloaded: {last_dl}")
            print(f"    - Errors: {has_errors}")
    except Exception as e:
        print(f"❌ Error fetching sitemaps: {e}")

def submit_sitemap(webmasters_service, site_url, sitemap_url):
    print(f"\n🚀 Submitting sitemap: {sitemap_url} to {site_url}...")
    try:
        webmasters_service.sitemaps().submit(siteUrl=site_url, feedpath=sitemap_url).execute()
        print("✅ Sitemap successfully submitted to Google Search Console!")
    except Exception as e:
        print(f"❌ Error submitting sitemap: {e}")

def delete_sitemap(webmasters_service, site_url, sitemap_url):
    print(f"\n🗑️ Deleting sitemap: {sitemap_url} from {site_url}...")
    try:
        webmasters_service.sitemaps().delete(siteUrl=site_url, feedpath=sitemap_url).execute()
        print("✅ Sitemap successfully deleted from Google Search Console!")
    except Exception as e:
        print(f"❌ Error deleting sitemap: {e}")

def inspect_url(sc_service, site_url, inspect_url_target):
    print(f"\n🔍 Inspecting URL: {inspect_url_target}...")
    try:
        body = {
            "inspectionUrl": inspect_url_target,
            "siteUrl": site_url,
        }
        res = sc_service.urlInspection().index().inspect(body=body).execute()
        result = res.get("inspectionResult", {})
        index_status = result.get("indexStatusResult", {})
        
        print("\n📊 Inspection Result:")
        print(f"  • Verdict:              {index_status.get('verdict')}")
        print(f"  • Coverage State:       {index_status.get('coverageState')}")
        print(f"  • Indexing State:       {index_status.get('indexingState')}")
        print(f"  • User Canonical:       {index_status.get('userCanonical')}")
        print(f"  • Google Canonical:     {index_status.get('googleCanonical')}")
        print(f"  • Last Crawl Time:      {index_status.get('lastCrawlTime')}")
        print(f"  • Robots Directive:     {index_status.get('robotsTxtState')}")
        print(f"  • Page Fetch State:     {index_status.get('pageFetchState')}")
        return index_status
    except Exception as e:
        print(f"❌ Error inspecting URL: {e}")
        return None

def inspect_batch(sc_service, site_url, urls_list):
    print(f"\n🔬 Running Batch Inspection across {len(urls_list)} URLs...")
    print(f"{'URL':<50} {'Verdict':<10} {'Coverage State':<32} {'Indexing':<12}")
    print("-" * 108)
    results = []
    for u in urls_list:
        u = u.strip()
        if not u:
            continue
        try:
            body = {"inspectionUrl": u, "siteUrl": site_url}
            res = sc_service.urlInspection().index().inspect(body=body).execute()
            idx = res.get("inspectionResult", {}).get("indexStatusResult", {})
            v = idx.get("verdict", "UNKNOWN")
            cov = idx.get("coverageState", "UNKNOWN")
            state = idx.get("indexingState", "UNKNOWN")
            print(f"{u[:48]:<50} {v:<10} {cov[:30]:<32} {state:<12}")
            results.append({"url": u, "verdict": v, "coverage": cov, "indexing": state})
        except Exception as e:
            print(f"{u[:48]:<50} ERROR      {str(e)[:40]}")
    return results

def query_search_analytics(webmasters_service, site_url, days=28, dimension="query", limit=25, export_csv=None):
    from datetime import datetime, timedelta
    end_date = (datetime.utcnow() - timedelta(days=2)).strftime("%Y-%m-%d")
    start_date = (datetime.utcnow() - timedelta(days=days + 2)).strftime("%Y-%m-%d")

    print(f"\n📈 Querying Search Analytics: {start_date} to {end_date} (Dimension: {dimension}, Limit: {limit})")
    body = {
        "startDate": start_date,
        "endDate": end_date,
        "dimensions": [dimension],
        "rowLimit": limit,
    }

    try:
        res = webmasters_service.searchanalytics().query(siteUrl=site_url, body=body).execute()
        rows = res.get("rows", [])
        if not rows:
            print("  (No search traffic data available for this range)")
            return []

        header_label = dimension.capitalize()
        print(f"\n{header_label:<45} {'Clicks':<8} {'Impressions':<12} {'CTR':<8} {'Position':<8}")
        print("-" * 85)

        records = []
        for r in rows:
            dim_val = r.get("keys", [""])[0]
            clicks = int(r.get("clicks", 0))
            impressions = int(r.get("impressions", 0))
            ctr = f"{r.get('ctr', 0.0) * 100:.1f}%"
            pos = f"{r.get('position', 0.0):.1f}"
            print(f"{dim_val[:43]:<45} {clicks:<8} {impressions:<12} {ctr:<8} {pos:<8}")
            records.append({dimension: dim_val, "clicks": clicks, "impressions": impressions, "ctr": ctr, "position": pos})

        if export_csv:
            import csv
            with open(export_csv, "w", newline="", encoding="utf-8") as f:
                writer = csv.DictWriter(f, fieldnames=[dimension, "clicks", "impressions", "ctr", "position"])
                writer.writeheader()
                writer.writerows(records)
            print(f"\n💾 Search analytics saved to: {export_csv}")

        return records
    except Exception as e:
        print(f"❌ Error querying search analytics: {e}")
        return []

def main():
    parser = argparse.ArgumentParser(description="Google Search Console Enterprise CLI for Helmetsan")
    parser.add_argument("--list-sites", action="store_true", help="List all verified sites")
    parser.add_argument("--sitemaps", action="store_true", help="List submitted sitemaps")
    parser.add_argument("--submit-sitemap", type=str, help="Submit a sitemap URL")
    parser.add_argument("--delete-sitemap", type=str, help="Delete a sitemap URL from GSC")
    parser.add_argument("--inspect", type=str, help="Inspect a specific URL")
    parser.add_argument("--inspect-batch", type=str, help="Batch inspect comma-separated URLs or file path")
    parser.add_argument("--search-analytics", action="store_true", help="Query search traffic analytics")
    parser.add_argument("--dimension", type=str, default="query", choices=["query", "page", "country", "device"], help="Analytics dimension")
    parser.add_argument("--days", type=int, default=28, help="Days to query (default: 28)")
    parser.add_argument("--limit", type=int, default=25, help="Result limit (default: 25)")
    parser.add_argument("--export-csv", type=str, help="Export analytics to CSV file")
    parser.add_argument("--site-url", type=str, default="sc-domain:helmetsan.com", help="Target site URL or sc-domain in GSC")

    args = parser.parse_args()
    sc_service, webmasters_service = get_service()

    if args.list_sites:
        list_sites(webmasters_service)
        return

    if args.sitemaps:
        list_sitemaps(webmasters_service, args.site_url)

    if args.submit_sitemap:
        submit_sitemap(webmasters_service, args.site_url, args.submit_sitemap)

    if args.delete_sitemap:
        delete_sitemap(webmasters_service, args.site_url, args.delete_sitemap)

    if args.inspect:
        inspect_url(sc_service, args.site_url, args.inspect)

    if args.inspect_batch:
        if os.path.exists(args.inspect_batch):
            with open(args.inspect_batch, "r") as f:
                urls = [line.strip() for line in f if line.strip()]
        else:
            urls = [u.strip() for u in args.inspect_batch.split(",") if u.strip()]
        inspect_batch(sc_service, args.site_url, urls)

    if args.search_analytics:
        query_search_analytics(
            webmasters_service,
            args.site_url,
            days=args.days,
            dimension=args.dimension,
            limit=args.limit,
            export_csv=args.export_csv
        )

    if not any([args.list_sites, args.sitemaps, args.submit_sitemap, args.delete_sitemap, args.inspect, args.inspect_batch, args.search_analytics]):
        list_sites(webmasters_service)
        list_sitemaps(webmasters_service, args.site_url)

if __name__ == "__main__":
    main()
