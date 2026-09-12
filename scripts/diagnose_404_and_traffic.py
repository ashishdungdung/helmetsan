#!/usr/bin/env python3
"""
Deep GA4 & GSC Diagnostic: 404 Errors, Helmets Archive, and Direct Traffic Breakdown.
"""

import os
import sys
import json
from google.oauth2 import service_account
from googleapiclient.discovery import build

KEY_FILE = '/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb/secrets/ash-site-502901-cd0bf333dc7c.json'
PROPERTY_ID = '525320520'

def diagnose():
    credentials = service_account.Credentials.from_service_account_file(
        KEY_FILE,
        scopes=[
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly'
        ]
    )

    analytics = build('analyticsdata', 'v1beta', credentials=credentials)
    sc = build('searchconsole', 'v1', credentials=credentials)

    print("\n" + "="*90)
    print("  DEEP GA4 & GSC AUDIT: 404 ERRORS, HELMETS ARCHIVE & DIRECT TRAFFIC ANALYSIS")
    print("="*90)

    # 1. 404 Analysis
    print("\n[1. TOP 404 ERRORS (Page Title contains 'not found' or '404')] (Last 90 Days)")
    body_404 = {
        'dateRanges': [{'startDate': '90daysAgo', 'endDate': 'today'}],
        'dimensions': [
            {'name': 'pagePath'},
            {'name': 'pageTitle'},
            {'name': 'sessionSourceMedium'}
        ],
        'metrics': [
            {'name': 'screenPageViews'},
            {'name': 'sessions'},
            {'name': 'userEngagementDuration'}
        ],
        'dimensionFilter': {
            'filter': {
                'fieldName': 'pageTitle',
                'stringFilter': {
                    'matchType': 'CONTAINS',
                    'value': 'not found',
                    'caseSensitive': False
                }
            }
        },
        'limit': 50
    }

    try:
        res_404 = analytics.properties().runReport(property=f'properties/{PROPERTY_ID}', body=body_404).execute()
        rows = res_404.get('rows', [])
        print(f"Total 404 URLs found: {len(rows)}")
        print(f"{'Page Path':<50} {'Views':<8} {'Sessions':<10} {'Source/Medium':<25}")
        print("-" * 95)
        for r in rows[:25]:
            path = r['dimensionValues'][0]['value'][:48]
            views = r['metricValues'][0]['value']
            sess = r['metricValues'][1]['value']
            src = r['dimensionValues'][2]['value'][:24]
            print(f"{path:<50} {views:<8} {sess:<10} {src:<25}")
    except Exception as e:
        print(f"Error querying 404s: {e}")

    # 2. Top Landing Pages Overall
    print("\n[2. TOP 20 LANDING PAGES OVERALL] (Last 90 Days)")
    body_landing = {
        'dateRanges': [{'startDate': '90daysAgo', 'endDate': 'today'}],
        'dimensions': [
            {'name': 'landingPage'},
            {'name': 'sessionSourceMedium'}
        ],
        'metrics': [
            {'name': 'sessions'},
            {'name': 'activeUsers'},
            {'name': 'userEngagementDuration'}
        ],
        'orderBys': [{'metric': {'metricName': 'sessions'}, 'desc': True}],
        'limit': 20
    }
    try:
        res_land = analytics.properties().runReport(property=f'properties/{PROPERTY_ID}', body=body_landing).execute()
        print(f"{'Landing Page':<50} {'Sessions':<10} {'Users':<8} {'Avg Dur':<10} {'Source/Medium':<25}")
        print("-" * 105)
        for r in res_land.get('rows', []):
            lp = r['dimensionValues'][0]['value'][:48]
            sess = int(r['metricValues'][0]['value'])
            u = int(r['metricValues'][1]['value'])
            dur = float(r['metricValues'][2]['value'])
            avg_dur = f"{dur/max(1, sess):.1f}s"
            src = r['dimensionValues'][1]['value'][:24]
            print(f"{lp:<50} {sess:<10} {u:<8} {avg_dur:<10} {src:<25}")
    except Exception as e:
        print(f"Error querying landing pages: {e}")

    # 3. Helmets Archive Deep Dive (/helmets/)
    print("\n[3. HELMETS ARCHIVE TRAFFIC PROFILE (/helmets/)] (Last 90 Days)")
    body_archive = {
        'dateRanges': [{'startDate': '90daysAgo', 'endDate': 'today'}],
        'dimensions': [
            {'name': 'pagePath'},
            {'name': 'country'},
            {'name': 'sessionSourceMedium'},
            {'name': 'screenResolution'}
        ],
        'metrics': [
            {'name': 'screenPageViews'},
            {'name': 'sessions'},
            {'name': 'userEngagementDuration'}
        ],
        'dimensionFilter': {
            'filter': {
                'fieldName': 'pagePath',
                'stringFilter': {
                    'matchType': 'CONTAINS',
                    'value': '/helmets',
                    'caseSensitive': False
                }
            }
        },
        'orderBys': [{'metric': {'metricName': 'screenPageViews'}, 'desc': True}],
        'limit': 20
    }
    try:
        res_arch = analytics.properties().runReport(property=f'properties/{PROPERTY_ID}', body=body_archive).execute()
        print(f"{'Path':<32} {'Country':<15} {'Resolution':<12} {'Views':<8} {'Avg Dur':<10} {'Source/Med':<20}")
        print("-" * 100)
        for r in res_arch.get('rows', []):
            p = r['dimensionValues'][0]['value'][:30]
            c = r['dimensionValues'][1]['value'][:14]
            res = r['dimensionValues'][3]['value'][:11]
            v = int(r['metricValues'][0]['value'])
            s = int(r['metricValues'][1]['value'])
            dur = float(r['metricValues'][2]['value'])
            avg_dur = f"{dur/max(1, s):.1f}s"
            src = r['dimensionValues'][2]['value'][:19]
            print(f"{p:<32} {c:<15} {res:<12} {v:<8} {avg_dur:<10} {src:<20}")
    except Exception as e:
        print(f"Error querying archive: {e}")

    # 4. Direct Traffic Deep Dive
    print("\n[4. DIRECT TRAFFIC BREAKDOWN: WHAT ARE DIRECT VISITORS ACCESSING?] (Last 90 Days)")
    body_direct = {
        'dateRanges': [{'startDate': '90daysAgo', 'endDate': 'today'}],
        'dimensions': [
            {'name': 'landingPage'},
            {'name': 'country'},
            {'name': 'screenResolution'},
            {'name': 'operatingSystem'}
        ],
        'metrics': [
            {'name': 'sessions'},
            {'name': 'screenPageViews'},
            {'name': 'userEngagementDuration'}
        ],
        'dimensionFilter': {
            'filter': {
                'fieldName': 'sessionSourceMedium',
                'stringFilter': {
                    'matchType': 'EXACT',
                    'value': '(direct) / (none)'
                }
            }
        },
        'orderBys': [{'metric': {'metricName': 'sessions'}, 'desc': True}],
        'limit': 20
    }
    try:
        res_dir = analytics.properties().runReport(property=f'properties/{PROPERTY_ID}', body=body_direct).execute()
        print(f"{'Landing Page':<40} {'Country':<14} {'OS':<10} {'Res':<11} {'Sess':<8} {'Avg Dur':<9}")
        print("-" * 95)
        for r in res_dir.get('rows', []):
            lp = r['dimensionValues'][0]['value'][:38]
            c = r['dimensionValues'][1]['value'][:13]
            os_name = r['dimensionValues'][3]['value'][:9]
            res = r['dimensionValues'][2]['value'][:10]
            s = int(r['metricValues'][0]['value'])
            dur = float(r['metricValues'][2]['value'])
            avg_dur = f"{dur/max(1, s):.1f}s"
            print(f"{lp:<40} {c:<14} {os_name:<10} {res:<11} {s:<8} {avg_dur:<9}")
    except Exception as e:
        print(f"Error querying direct traffic: {e}")

    # 5. GSC
    print("\n[5. GOOGLE SEARCH CONSOLE ORGANIC PERFORMANCE] (Last 90 Days)")
    try:
        gsc_query = {
            'startDate': '2026-06-12',
            'endDate': '2026-09-10',
            'dimensions': ['query'],
            'rowLimit': 15
        }
        res_sc_q = sc.searchanalytics().query(siteUrl='sc-domain:helmetsan.com', body=gsc_query).execute()
        print(f"{'Query':<40} {'Clicks':<8} {'Impressions':<12} {'CTR':<8} {'Position':<8}")
        print("-" * 80)
        for row in res_sc_q.get('rows', []):
            q = row['keys'][0][:38]
            clicks = row.get('clicks', 0)
            imp = row.get('impressions', 0)
            ctr = f"{row.get('ctr', 0)*100:.1f}%"
            pos = f"{row.get('position', 0):.1f}"
            print(f"{q:<40} {clicks:<8} {imp:<12} {ctr:<8} {pos:<8}")

        print("\n[TOP 10 GOOGLE SEARCH CONSOLE PAGES]")
        gsc_page = {
            'startDate': '2026-06-12',
            'endDate': '2026-09-10',
            'dimensions': ['page'],
            'rowLimit': 15
        }
        res_sc_p = sc.searchanalytics().query(siteUrl='sc-domain:helmetsan.com', body=gsc_page).execute()
        print(f"{'Page URL':<60} {'Clicks':<8} {'Impressions':<12} {'CTR':<8}")
        print("-" * 90)
        for row in res_sc_p.get('rows', []):
            p = row['keys'][0][:58]
            clicks = row.get('clicks', 0)
            imp = row.get('impressions', 0)
            ctr = f"{row.get('ctr', 0)*100:.1f}%"
            print(f"{p:<60} {clicks:<8} {imp:<12} {ctr:<8}")
    except Exception as e:
        print(f"Error querying GSC: {e}")

if __name__ == '__main__':
    diagnose()
