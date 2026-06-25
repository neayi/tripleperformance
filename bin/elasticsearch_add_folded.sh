#!/bin/bash
# Adds the folded_text analyzer and .folded subfields to all smw-data-* ElasticSearch indices.
# Run from the project root: ./bin/elasticsearch_add_folded.sh

set -e

# Extract credentials from ELASTICSEARCH_SERVER env var (format: user:pass@host)
ES_SERVER=$(grep ELASTICSEARCH_SERVER .env | cut -d= -f2-)
ES_AUTH=$(echo "$ES_SERVER" | sed 's/@.*//')        # user:pass
ES_HOST=$(echo "$ES_SERVER" | sed 's/.*@//')         # hostname

ES_URL="http://${ES_AUTH}@${ES_HOST}:9200"

echo "Connecting to ElasticSearch at http://${ES_HOST}:9200"

# Get all smw-data-* indices
INDICES=$(curl -s "${ES_URL}/_cat/indices/smw-data-*?h=index" | tr -d ' ')

if [ -z "$INDICES" ]; then
    echo "ERROR: No smw-data-* indices found. Check credentials and connectivity."
    exit 1
fi

echo "Found indices:"
echo "$INDICES"
echo ""

for INDEX in $INDICES; do
    echo "=========================================="
    echo "Processing: $INDEX"
    echo "=========================================="

    # 1. Close index
    echo "  [1/4] Closing index..."
    curl -s -X POST "${ES_URL}/${INDEX}/_close" | python3 -c "import json,sys; d=json.load(sys.stdin); print('  OK' if d.get('acknowledged') else f'  WARN: {d}')"

    # 2. Add folded_text analyzer
    echo "  [2/4] Adding folded_text analyzer..."
    curl -s -X PUT "${ES_URL}/${INDEX}/_settings" \
      -H "Content-Type: application/json" -d '{
        "analysis": {
          "analyzer": {
            "folded_text": {
              "type": "custom",
              "tokenizer": "standard",
              "filter": ["lowercase", "asciifolding"]
            }
          }
        }
      }' | python3 -c "import json,sys; d=json.load(sys.stdin); print('  OK' if d.get('acknowledged') else f'  WARN: {d}')"

    # 3. Reopen index
    echo "  [3/4] Reopening index..."
    curl -s -X POST "${ES_URL}/${INDEX}/_open" | python3 -c "import json,sys; d=json.load(sys.stdin); print('  OK' if d.get('acknowledged') else f'  WARN: {d}')"

    # 4. Add .folded subfields to mapping
    echo "  [4/4] Updating mapping..."
    curl -s -X PUT "${ES_URL}/${INDEX}/_mapping" \
      -H "Content-Type: application/json" -d '{
        "properties": {
          "subject": {
            "properties": {
              "title": {
                "type": "text",
                "fields": {
                  "folded": { "type": "text", "analyzer": "folded_text" }
                }
              }
            }
          },
          "text_copy": {
            "type": "text",
            "fields": {
              "folded": { "type": "text", "analyzer": "folded_text" }
            }
          }
        }
      }' | python3 -c "import json,sys; d=json.load(sys.stdin); print('  OK' if d.get('acknowledged') else f'  WARN: {d}')"

    echo "  Done: $INDEX"
    echo ""
done

echo "=========================================="
echo "All indices updated. Launching reindexing in background..."
echo "=========================================="

TASK_IDS=()
for INDEX in $INDICES; do
    TASK=$(curl -s -X POST \
      "${ES_URL}/${INDEX}/_update_by_query?conflicts=proceed&wait_for_completion=false" \
      -H "Content-Type: application/json" -d '{"query":{"match_all":{}}}' \
      | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('task','ERROR'))")
    echo "  $INDEX → task: $TASK"
    TASK_IDS+=("$INDEX:$TASK")
done

echo ""
echo "Monitor progress with:"
for ENTRY in "${TASK_IDS[@]}"; do
    IDX=$(echo "$ENTRY" | cut -d: -f1)
    TASK=$(echo "$ENTRY" | cut -d: -f2-)
    echo "  curl -s '${ES_URL}/_tasks/${TASK}' | python3 -c \"import json,sys; d=json.load(sys.stdin); s=d.get('task',{}).get('status',{}); print('${IDX}: completed='+str(d.get('completed'))+' '+str(s.get('updated',0))+'/'+str(s.get('total',0)))\""
done
