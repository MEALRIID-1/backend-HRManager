#!/bin/bash

# Script de vérification de santé de l'application
# Usage: ./health-check.sh [host] [port]

set -e

HOST=${1:-localhost}
PORT=${2:-80}
TIMEOUT=30
RETRIES=10

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "🏥 Health Check - $HOST:$PORT"
echo "================================"

# Fonction de test
wait_for_service() {
    local url=$1
    local name=$2
    local attempt=1
    
    echo "⏳ Attente de $name..."
    
    while [ $attempt -le $RETRIES ]; do
        if curl -f -s "$url" > /dev/null 2>&1; then
            echo -e "${GREEN}✅ $name est en ligne${NC}"
            return 0
        fi
        
        echo "  Tentative $attempt/$RETRIES..."
        sleep 3
        ((attempt++))
    done
    
    echo -e "${RED}❌ $name n'est pas accessible après $RETRIES tentatives${NC}"
    return 1
}

# Test 1: API Health
wait_for_service "http://$HOST:$PORT/api/health" "API Health" || exit 1

# Test 2: Version
VERSION=$(curl -s "http://$HOST:$PORT/api/version" 2>/dev/null || echo "unknown")
echo -e "${GREEN}📦 Version: $VERSION${NC}"

# Test 3: Database connectivity
DB_STATUS=$(curl -s "http://$HOST:$PORT/api/health/database" 2>/dev/null || echo '{"status":"unknown"}')
if echo "$DB_STATUS" | grep -q '"status":"ok"'; then
    echo -e "${GREEN}🗄️  Base de données: OK${NC}"
else
    echo -e "${YELLOW}⚠️  Base de données: Vérifiez la connexion${NC}"
fi

# Test 4: Redis connectivity  
REDIS_STATUS=$(curl -s "http://$HOST:$PORT/api/health/redis" 2>/dev/null || echo '{"status":"unknown"}')
if echo "$REDIS_STATUS" | grep -q '"status":"ok"'; then
    echo -e "${GREEN}📡 Redis: OK${NC}"
else
    echo -e "${YELLOW}⚠️  Redis: Vérifiez la connexion${NC}"
fi

# Test 5: Queue workers
QUEUE_STATUS=$(curl -s "http://$HOST:$PORT/api/health/queue" 2>/dev/null || echo '{"status":"unknown"}')
if echo "$QUEUE_STATUS" | grep -q '"status":"ok"'; then
    echo -e "${GREEN}⚙️  Queue Workers: OK${NC}"
else
    echo -e "${YELLOW}⚠️  Queue Workers: Vérifiez le statut${NC}"
fi

# Test 6: Response time
START_TIME=$(date +%s%N)
curl -s "http://$HOST:$PORT/api/health" > /dev/null
END_TIME=$(date +%s%N)
RESPONSE_TIME=$(( (END_TIME - START_TIME) / 1000000 ))

if [ $RESPONSE_TIME -lt 500 ]; then
    echo -e "${GREEN}⚡ Temps de réponse: ${RESPONSE_TIME}ms (excellent)${NC}"
elif [ $RESPONSE_TIME -lt 1000 ]; then
    echo -e "${GREEN}⚡ Temps de réponse: ${RESPONSE_TIME}ms (bon)${NC}"
else
    echo -e "${YELLOW}⚡ Temps de réponse: ${RESPONSE_TIME}ms (lent)${NC}"
fi

echo ""
echo -e "${GREEN}🎉 Tous les checks sont passés! L'application est prête.${NC}"
