#!/usr/bin/env python3
"""
Helmetsan Live Affiliate Telemetry & Conversion Report
Queries the production `wp_helmetsan_clicks` table directly via SSH
to generate real-time metrics on affiliate network traffic, conversions, and country distribution.
"""

import subprocess
import sys

def run_remote_query(sql):
    cmd = [
        "ssh", "-o", "ControlMaster=no", "-o", "ControlPath=none", "-o", "StrictHostKeyChecking=no",
        "root@31.70.136.154",
        f"""cd /var/www/helmetsan.com/public && sudo -u www-data wp db query "{sql}" """
    ]
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return res.stdout.strip()

def main():
    print("=" * 70)
    print(" 🚀 HELMETSAN LIVE AFFILIATE TELEMETRY & CONVERSION REPORT")
    print("=" * 70)

    # 1. Total & Today
    sql_summary = """
    SELECT 
        COUNT(*) as total_clicks,
        COUNT(CASE WHEN created_at >= CURDATE() THEN 1 END) as clicks_today,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as clicks_last_7_days,
        COUNT(DISTINCT helmet_id) as unique_helmets_clicked,
        COUNT(DISTINCT ip_hash) as unique_visitors
    FROM wp_helmetsan_clicks;
    """
    print("\n📊 1. OVERALL CLICK ACTIVITY:")
    print(run_remote_query(sql_summary))

    # 2. Affiliate Network Breakdown
    sql_networks = """
    SELECT 
        affiliate_network,
        marketplace_id,
        COUNT(*) as clicks,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wp_helmetsan_clicks)), 2) as pct_total
    FROM wp_helmetsan_clicks
    GROUP BY affiliate_network, marketplace_id
    ORDER BY clicks DESC
    LIMIT 15;
    """
    print("\n🌐 2. TOP AFFILIATE NETWORKS & MARKETPLACES:")
    print(run_remote_query(sql_networks))

    # 3. Top 10 Clicked Helmets
    sql_top_helmets = """
    SELECT 
        c.helmet_id,
        p.post_title,
        p.post_name as slug,
        COUNT(*) as total_clicks,
        COUNT(CASE WHEN c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as clicks_7d
    FROM wp_helmetsan_clicks c
    LEFT JOIN wp_posts p ON c.helmet_id = p.ID
    GROUP BY c.helmet_id, p.post_title, p.post_name
    ORDER BY total_clicks DESC
    LIMIT 10;
    """
    print("\n🏆 3. TOP 10 CLICKED HELMETS / GEAR:")
    print(run_remote_query(sql_top_helmets))

    print("\n" + "=" * 70)
    print(" ✅ Telemetry snapshot generated successfully.")
    print("=" * 70)

if __name__ == "__main__":
    main()
