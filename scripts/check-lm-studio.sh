#!/bin/bash
# Check if LM Studio is reachable

LM_URL="${1:-http://192.168.2.240:1234/v1/models}"

echo "Checking LM Studio health at ${LM_URL}..."

# Try to fetch models with a 5s timeout
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "${LM_URL}")

if [ "$RESPONSE" == "200" ]; then
    echo "✅ LM Studio is ONLINE and responding."
    exit 0
else
    echo "❌ LM Studio is OFFLINE or UNREACHABLE (HTTP ${RESPONSE})."
    echo "Please ensure LM Studio is running and 'Local Server' is enabled on port 1234."
    exit 1
fi
