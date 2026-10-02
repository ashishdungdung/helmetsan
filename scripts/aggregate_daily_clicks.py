#!/usr/bin/env python3
"""
Helmetsan Daily Click Aggregator
Rolls up raw click events from `wp_helmetsan_clicks` into `wp_helmetsan_clicks_daily`
for sub-millisecond reporting and analytics.
"""

import sys
import subprocess
import argparse
from datetime import datetime, timedelta

REMOTE_HOST = "root@31.70.136.154"
DB_NAME = "wp_helmetsan_com"

def run_remote_query(sql):
    cmd = [
        "ssh", "-o", "ControlMaster=no", "-o", "ControlPath=none", "-o", "StrictHostKeyChecking=no",
        REMOTE_HOST,
        f"mariadb {DB_NAME} -e \"{sql}\""
    ]
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return res.stdout.strip()

def aggregate_range(start_date, end_date):
    print(f"🔄 Aggregating clicks from {start_date} to {end_date}...")
    
    sql = f"""
    INSERT INTO wp_helmetsan_clicks_daily (
        click_date, affiliate_network, marketplace_id, country_iso, device_type,
        total_clicks, bot_clicks, unique_visitors
    )
    SELECT
        DATE(created_at) as click_date,
        affiliate_network,
        marketplace_id,
        country_iso,
        device_type,
        COUNT(*) as total_clicks,
        SUM(is_bot) as bot_clicks,
        COUNT(DISTINCT NULLIF(ip_hash, '')) as unique_visitors
    FROM wp_helmetsan_clicks
    WHERE created_at >= '{start_date} 00:00:00' AND created_at <= '{end_date} 23:59:59'
    GROUP BY DATE(created_at), affiliate_network, marketplace_id, country_iso, device_type
    ON DUPLICATE KEY UPDATE
        total_clicks = VALUES(total_clicks),
        bot_clicks = VALUES(bot_clicks),
        unique_visitors = VALUES(unique_visitors);
    """
    
    run_remote_query(sql)
    print(f"✅ Aggregation complete for {start_date} to {end_date}.")

def display_summary(days=7):
    print(f"\n📊 Summary of Last {days} Days in wp_helmetsan_clicks_daily:")
    sql = f"""
    SELECT
        click_date,
        SUM(total_clicks) as total,
        SUM(bot_clicks) as bot,
        SUM(unique_visitors) as visitors
    FROM wp_helmetsan_clicks_daily
    WHERE click_date >= DATE_SUB(CURDATE(), INTERVAL {days} DAY)
    GROUP BY click_date
    ORDER BY click_date DESC;
    """
    out = run_remote_query(sql)
    print(out)

def main():
    parser = argparse.ArgumentParser(description="Aggregate raw clicks into daily summary table.")
    parser.add_argument("--days", type=int, default=30, help="Number of past days to aggregate (default: 30)")
    parser.add_argument("--all", action="store_true", help="Aggregate entire historical click log")
    parser.add_argument("--date", type=str, help="Specific date to aggregate (YYYY-MM-DD)")
    args = parser.parse_args()

    if args.date:
        aggregate_range(args.date, args.date)
    elif args.all:
        print("⚡ Backfilling all historical clicks...")
        # Get min and max date
        min_max = run_remote_query("SELECT MIN(DATE(created_at)), MAX(DATE(created_at)) FROM wp_helmetsan_clicks;")
        lines = min_max.split("\n")
        if len(lines) > 1:
            parts = lines[1].split("\t")
            start_date, end_date = parts[0], parts[1]
            aggregate_range(start_date, end_date)
    else:
        end_dt = datetime.utcnow()
        start_dt = end_dt - timedelta(days=args.days)
        aggregate_range(start_dt.strftime("%Y-%m-%d"), end_dt.strftime("%Y-%m-%d"))

    display_summary(days=min(args.days, 14))

if __name__ == "__main__":
    main()
