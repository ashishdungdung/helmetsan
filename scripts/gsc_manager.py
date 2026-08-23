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
    # Look for specific names first
    for f in os.listdir(root_dir):
        if f.endswith(".json") and ("ash-" in f or "service-account" in f or "gsc" in f):
            full_path = os.path.join(root_dir, f)
            try:
                with open(full_path, "r") as fp:
                    data = json.load(fp)
                    if data.get("type") == "service_account":
                        return full_path
            except Exception:
                continue
    # Default fallback
    return os.path.join(root_dir, "ash-site-502901.json")

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
    except Exception as e:
        print(f"❌ Error inspecting URL: {e}")

def main():
    parser = argparse.ArgumentParser(description="Google Search Console Manager for Helmetsan")
    parser.add_argument("--list-sites", action="store_true", help="List all verified sites")
    parser.add_argument("--sitemaps", action="store_true", help="List submitted sitemaps")
    parser.add_argument("--submit-sitemap", type=str, help="Submit a sitemap URL (e.g. https://helmetsan.com/sitemap_index.xml)")
    parser.add_argument("--inspect", type=str, help="Inspect a specific URL")
    parser.add_argument("--site-url", type=str, default="https://helmetsan.com/", help="Target site URL or sc-domain in GSC")

    args = parser.parse_args()
    sc_service, webmasters_service = get_service()

    if args.list_sites or (not args.sitemaps and not args.submit_sitemap and not args.inspect):
        sites = list_sites(webmasters_service)
        if sites and args.site_url not in sites and f"sc-domain:{args.site_url.replace('https://', '').replace('/', '')}" in sites:
            args.site_url = f"sc-domain:{args.site_url.replace('https://', '').replace('/', '')}"

    if args.sitemaps:
        list_sitemaps(webmasters_service, args.site_url)

    if args.submit_sitemap:
        submit_sitemap(webmasters_service, args.site_url, args.submit_sitemap)

    if args.inspect:
        inspect_url(sc_service, args.site_url, args.inspect)

if __name__ == "__main__":
    main()
