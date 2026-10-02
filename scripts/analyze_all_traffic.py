#!/usr/bin/env python3
"""
Comprehensive Traffic Deep-Dive: GA4 + GSC + Origin Server Logs
Analyzes:
1. Past 10 Days vs Past 30 Days GA4 Traffic (Countries, Cities, Paths, Sources, Tech)
2. GSC Clicks, Impressions, CTR, Top Pages, Top Queries
3. Server-side log patterns (Legit AI Crawlers vs Bots vs Direct Scrapers)
"""

import os
import sys
import json
from datetime import datetime, timedelta
from collections import defaultdict
from google.oauth2 import service_account
from googleapiclient.discovery import build

KEY_FILE = '/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb/secrets/ash-site-502901-cd0bf333dc7c.json'
PROPERTY_ID = '525320520'
GSC_SITE = 'sc-domain:helmetsan.com'

def run_ga4_report(analytics, body):
    try:
        return analytics.properties().runReport(property=f'properties/{PROPERTY_ID}', body=body).execute()
    except Exception as e:
        print(f"GA4 Error: {e}", file=sys.stderr)
        return {}

def main():
    credentials = service_account.Credentials.from_service_account_file(
        KEY_FILE,
        scopes=[
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly'
        ]
    )

    analytics = build('analyticsdata', 'v1beta', credentials=credentials)
    sc = build('searchconsole', 'v1', credentials=credentials)

    print("=" * 80)
    print(" HELMETSAN.COM DEEP TRAFFIC ANALYSIS (PAST 10 DAYS)")
    print("=" * 80)

    # 1. Daily Trend over Past 10 Days
    print("\n--- 1. DAILY TRAFFIC TREND (PAST 10 DAYS) ---")
    daily_res = run_ga4_report(analytics, {
        'dateRanges': [{'startDate': '10daysAgo', 'endDate': 'today'}],
        'dimensions': [{'name': 'date'}],
        'metrics': [
            {'name': 'activeUsers'},
            {'name': 'sessions'},
            {'name': 'screenPageViews'},
            {'name': 'userEngagementDuration'}
        ],
        'orderBys': [{'dimension': {'dimensionName': 'date'}, 'desc': False}]
    })

    print(f"{'Date':12s} | {'Users':>6s} | {'Sessions':>8s} | {'Pageviews':>9s} | {'Avg Duration':>12s}")
    print("-" * 56)
    for r in daily_res.get('rows', []):
        dt = r['dimensionValues'][0]['value']
        # format YYYYMMDD to YYYY-MM-DD
        dt_fmt = f"{dt[:4]}-{dt[4:6]}-{dt[6:]}" if len(dt) == 8 else dt
        users = int(r['metricValues'][0]['value'])
        sess = int(r['metricValues'][1]['value'])
        pv = int(r['metricValues'][2]['value'])
        dur = float(r['metricValues'][3]['value'])
        avg_dur = f"{(dur / sess):.1f}s" if sess > 0 else "0s"
        print(f"{dt_fmt:12s} | {users:6d} | {sess:8d} | {pv:9d} | {avg_dur:>12s}")

    # 2. Country Breakdown (Past 10 Days)
    print("\n--- 2. GEOGRAPHIC DISTRIBUTION: TOP COUNTRIES (PAST 10 DAYS) ---")
    geo_res = run_ga4_report(analytics, {
        'dateRanges': [{'startDate': '10daysAgo', 'endDate': 'today'}],
        'dimensions': [{'name': 'country'}],
        'metrics': [
            {'name': 'activeUsers'},
            {'name': 'sessions'},
            {'name': 'screenPageViews'},
            {'name': 'userEngagementDuration'},
            {'name': 'bounceRate'}
        ],
        'orderBys': [{'metric': {'metricName': 'sessions'}, 'desc': True}],
        'limit': 15
    })

    print(f"{'Country':24s} | {'Users':>6s} | {'Sessions':>8s} | {'PVs':>7s} | {'Avg Dur':>8s} | {'Bounce':>7s}")
    print("-" * 72)
    for r in geo_res.get('rows', []):
        c = r['dimensionValues'][0]['value']
        users = int(r['metricValues'][0]['value'])
        sess = int(r['metricValues'][1]['value'])
        pv = int(r['metricValues'][2]['value'])
        dur = float(r['metricValues'][3]['value'])
        bounce = float(r['metricValues'][4]['value']) * 100
        avg_dur = f"{(dur / sess):.1f}s" if sess > 0 else "0s"
        print(f"{c[:24]:24s} | {users:6d} | {sess:8d} | {pv:7d} | {avg_dur:>8s} | {bounce:>6.1f}%")

    # 3. City Breakdown (Past 10 Days)
    print("\n--- 3. TOP CITIES (PAST 10 DAYS) ---")
    city_res = run_ga4_report(analytics, {
        'dateRanges': [{'startDate': '10daysAgo', 'endDate': 'today'}],
        'dimensions': [{'name': 'country'}, {'name': 'city'}],
        'metrics': [
            {'name': 'activeUsers'},
            {'name': 'sessions'},
            {'name': 'screenPageViews'},
            {'name': 'userEngagementDuration'}
        ],
        'orderBys': [{'metric': {'metricName': 'sessions'}, 'desc': True}],
        'limit': 15
    })

    print(f"{'City':22s} | {'Country':18s} | {'Users':>6s} | {'Sessions':>8s} | {'Avg Dur':>8s}")
    print("-" * 72)
    for r in city_res.get('rows', []):
        country = r['dimensionValues'][0]['value']
        city = r['dimensionValues'][1]['value']
        users = int(r['metricValues'][0]['value'])
        sess = int(r['metricValues'][1]['value'])
        dur = float(r['metricValues'][3]['value'])
        avg_dur = f"{(dur / sess):.1f}s" if sess > 0 else "0s"
        print(f"{city[:22]:22s} | {country[:18]:18s} | {users:6d} | {sess:8d} | {avg_dur:>8s}")

    # 4. Traffic Acquisition Sources & Mediums
    print("\n--- 4. TRAFFIC ACQUISITION SOURCES & MEDIUMS (PAST 10 DAYS) ---")
    acq_res = run_ga4_report(analytics, {
        'dateRanges': [{'startDate': '10daysAgo', 'endDate': 'today'}],
        'dimensions': [{'name': 'sessionSourceMedium'}, {'name': 'sessionDefaultChannelGroup'}],
        'metrics': [
            {'name': 'activeUsers'},
            {'name': 'sessions'},
            {'name': 'screenPageViews'},
            {'name': 'userEngagementDuration'}
        ],
        'orderBys': [{'metric': {'metricName': 'sessions'}, 'desc': True}],
        'limit': 15
    })

    print(f"{'Source / Medium':32s} | {'Channel':16s} | {'Users':>6s} | {'Sessions':>8s} | {'Avg Dur':>8s}")
    print("-" * 76)
    for r in acq_res.get('rows', []):
        sm = r['dimensionValues'][0]['value']
        ch = r['dimensionValues'][1]['value']
        users = int(r['metricValues'][0]['value'])
        sess = int(r['metricValues'][1]['value'])
        dur = float(r['metricValues'][3]['value'])
        avg_dur = f"{(dur / sess):.1f}s" if sess > 0 else "0s"
        print(f"{sm[:32]:32s} | {ch[:16]:16s} | {users:6d} | {sess:8d} | {avg_dur:>8s}")

    # 5. Top Visited Pages & Landing Pages
    print("\n--- 5. TOP VISITED PAGES (PAST 10 DAYS) ---")
    page_res = run_ga4_report(analytics, {
        'dateRanges': [{'startDate': '10daysAgo', 'endDate': 'today'}],
        'dimensions': [{'name': 'pagePath'}],
        'metrics': [
            {'name': 'activeUsers'},
            {'name': 'screenPageViews'},
            {'name': 'userEngagementDuration'}
        ],
        'orderBys': [{'metric': {'metricName': 'screenPageViews'}, 'desc': True}],
        'limit': 15
    })

    print(f"{'Page Path':48s} | {'Users':>6s} | {'Pageviews':>9s} | {'Avg Dur':>8s}")
    print("-" * 75)
    for r in page_res.get('rows', []):
        p = r['dimensionValues'][0]['value']
        users = int(r['metricValues'][0]['value'])
        pv = int(r['metricValues'][1]['value'])
        dur = float(r['metricValues'][2]['value'])
        avg_dur = f"{(dur / pv):.1f}s" if pv > 0 else "0s"
        print(f"{p[:48]:48s} | {users:6d} | {pv:9d} | {avg_dur:>8s}")

    # 6. Operating System & Browser (Past 10 Days)
    print("\n--- 6. DEVICE / OS / BROWSER BREAKDOWN (PAST 10 DAYS) ---")
    tech_res = run_ga4_report(analytics, {
        'dateRanges': [{'startDate': '10daysAgo', 'endDate': 'today'}],
        'dimensions': [{'name': 'operatingSystem'}, {'name': 'browser'}, {'name': 'deviceCategory'}],
        'metrics': [
            {'name': 'sessions'},
            {'name': 'activeUsers'}
        ],
        'orderBys': [{'metric': {'metricName': 'sessions'}, 'desc': True}],
        'limit': 10
    })

    print(f"{'OS':14s} | {'Browser':16s} | {'Device':10s} | {'Sessions':>8s} | {'Users':>6s}")
    print("-" * 62)
    for r in tech_res.get('rows', []):
        os_name = r['dimensionValues'][0]['value']
        browser = r['dimensionValues'][1]['value']
        device = r['dimensionValues'][2]['value']
        sess = int(r['metricValues'][0]['value'])
        users = int(r['metricValues'][1]['value'])
        print(f"{os_name[:14]:14s} | {browser[:16]:16s} | {device[:10]:10s} | {sess:8d} | {users:6d}")

    # 7. GSC Organic Search Performance
    print("\n--- 7. GOOGLE SEARCH CONSOLE: TOP PAGES & QUERIES (PAST 10 DAYS) ---")
    try:
        end_date = (datetime.now() - timedelta(days=2)).strftime('%Y-%m-%d')
        start_date = (datetime.now() - timedelta(days=12)).strftime('%Y-%m-%d')
        
        gsc_q = sc.searchanalytics().query(siteUrl=GSC_SITE, body={
            'startDate': start_date,
            'endDate': end_date,
            'dimensions': ['query'],
            'rowLimit': 10
        }).execute()
        
        gsc_p = sc.searchanalytics().query(siteUrl=GSC_SITE, body={
            'startDate': start_date,
            'endDate': end_date,
            'dimensions': ['page'],
            'rowLimit': 10
        }).execute()
        
        print(f"Period: {start_date} to {end_date}")
        print("\nTop Search Queries:")
        for row in gsc_q.get('rows', []):
            q = row['keys'][0]
            clicks = row.get('clicks', 0)
            impr = row.get('impressions', 0)
            pos = row.get('position', 0)
            print(f" - {q:35s} | Clicks: {clicks:2d} | Impr: {impr:3d} | Avg Pos: {pos:.1f}")

        print("\nTop Pages in Organic Search:")
        for row in gsc_p.get('rows', []):
            page = row['keys'][0].replace('https://helmetsan.com', '')
            clicks = row.get('clicks', 0)
            impr = row.get('impressions', 0)
            pos = row.get('position', 0)
            print(f" - {page:45s} | Clicks: {clicks:2d} | Impr: {impr:3d} | Avg Pos: {pos:.1f}")
    except Exception as e:
        print("GSC query failed:", e)

if __name__ == '__main__':
    main()
