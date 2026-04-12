#!/usr/bin/env bash
#
# CI3 Deployment Script
# Reads deploy.conf from the same directory and deploys via lftp.
# Usage: ./deploy.sh [--yes] [--dry-run]
#

set -euo pipefail

# ── Resolve script directory ──────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CONF_FILE="$SCRIPT_DIR/deploy.conf"
PROJECT_NAME="$(basename "$SCRIPT_DIR")"

# ── Parse flags ───────────────────────────────────────────────
AUTO_YES=false
DRY_RUN_ONLY=false

for arg in "$@"; do
    case "$arg" in
        --yes|-y) AUTO_YES=true ;;
        --dry-run) DRY_RUN_ONLY=true ;;
        --help|-h)
            echo "Usage: ./deploy.sh [--yes] [--dry-run]"
            echo "  --yes, -y    Skip confirmation prompts"
            echo "  --dry-run    Show what would change without deploying"
            exit 0
            ;;
        *)
            echo "Unknown flag: $arg"
            echo "Usage: ./deploy.sh [--yes] [--dry-run]"
            exit 1
            ;;
    esac
done

# ── Read config ───────────────────────────────────────────────
if [ ! -f "$CONF_FILE" ]; then
    echo "ERROR: deploy.conf not found at $CONF_FILE"
    echo "Copy deploy.conf.example to deploy.conf and fill in your deployment target."
    exit 1
fi

# Source config (key=value pairs)
source "$CONF_FILE"

# Validate required fields
if [ -z "${LFTP_BOOKMARK:-}" ]; then
    echo "ERROR: LFTP_BOOKMARK not set in deploy.conf"
    exit 1
fi

REMOTE_DIR="${REMOTE_DIR:-}"
PRODUCTION_URL="${PRODUCTION_URL:-}"
DEPLOY_VENDOR="${DEPLOY_VENDOR:-no}"
EXCLUDE_FILE="${EXCLUDE_FILE:-$HOME/.lftp/exclude-list}"
POST_DEPLOY_CMD="${POST_DEPLOY_CMD:-}"

if [ ! -f "$EXCLUDE_FILE" ]; then
    echo "ERROR: Exclude file not found at $EXCLUDE_FILE"
    exit 1
fi

# ── Git status check ─────────────────────────────────────────
echo ""
if git rev-parse --is-inside-work-tree &>/dev/null; then
    DIRTY=$(git status --porcelain 2>/dev/null)
    if [ -n "$DIRTY" ]; then
        echo "WARNING: Uncommitted changes detected:"
        echo "$DIRTY"
        echo ""
        if [ "$AUTO_YES" = false ]; then
            read -rp "Deploy with uncommitted changes? (y/N) " answer
            if [[ ! "$answer" =~ ^[Yy]$ ]]; then
                echo "Aborted."
                exit 0
            fi
        else
            echo "Proceeding with uncommitted changes (--yes flag)."
        fi
    else
        echo "Git: clean working tree"
    fi
else
    echo "Git: not a git repository, skipping check"
fi

# ── Deployment summary ───────────────────────────────────────
VENDOR_STATUS="excluded"
if [ "$DEPLOY_VENDOR" = "yes" ]; then
    VENDOR_STATUS="included"
fi

TARGET_DISPLAY="$LFTP_BOOKMARK"
if [ -n "$REMOTE_DIR" ]; then
    TARGET_DISPLAY="$LFTP_BOOKMARK / $REMOTE_DIR"
fi

echo ""
echo "┌──────────────────────────────────────────────┐"
echo "│ DEPLOY: $PROJECT_NAME"
echo "│ Target: $TARGET_DISPLAY"
if [ -n "$PRODUCTION_URL" ]; then
echo "│ URL:    $PRODUCTION_URL"
fi
echo "│ Vendor: $VENDOR_STATUS"
if [ -n "$POST_DEPLOY_CMD" ]; then
echo "│ Post:   $POST_DEPLOY_CMD"
fi
echo "└──────────────────────────────────────────────┘"
echo ""

if [ "$AUTO_YES" = false ]; then
    read -rp "Press Enter to run dry-run, or Ctrl+C to abort... "
fi

# ── Build lftp command ───────────────────────────────────────
MIRROR_CMD="mirror --reverse --delete --exclude-glob-from $EXCLUDE_FILE"

# Exclude vendor/ unless DEPLOY_VENDOR=yes
if [ "$DEPLOY_VENDOR" != "yes" ]; then
    MIRROR_CMD="$MIRROR_CMD --exclude vendor/"
fi

# Exclude deploy files themselves
MIRROR_CMD="$MIRROR_CMD --exclude deploy.conf --exclude deploy.sh --exclude deploy.conf.example"

# ── Dry run ──────────────────────────────────────────────────
echo "── Dry Run ──────────────────────────────────────"
echo ""

if [ -n "$REMOTE_DIR" ]; then
    lftp "$LFTP_BOOKMARK" -e "cd $REMOTE_DIR && $MIRROR_CMD --dry-run . .; quit"
else
    lftp "$LFTP_BOOKMARK" -e "$MIRROR_CMD --dry-run . .; quit"
fi

echo ""
echo "── End Dry Run ──────────────────────────────────"
echo ""

if [ "$DRY_RUN_ONLY" = true ]; then
    echo "Dry run complete. No files were transferred."
    exit 0
fi

if [ "$AUTO_YES" = false ]; then
    read -rp "Proceed with deploy? (y/N) " answer
    if [[ ! "$answer" =~ ^[Yy]$ ]]; then
        echo "Aborted."
        exit 0
    fi
fi

# ── Deploy ───────────────────────────────────────────────────
echo ""
echo "Deploying..."
echo ""

if [ -n "$REMOTE_DIR" ]; then
    lftp "$LFTP_BOOKMARK" -e "cd $REMOTE_DIR && $MIRROR_CMD . .; quit"
else
    lftp "$LFTP_BOOKMARK" -e "$MIRROR_CMD . .; quit"
fi

echo ""
echo "Deploy complete."

# ── Post-deploy command ──────────────────────────────────────
if [ -n "$POST_DEPLOY_CMD" ]; then
    echo ""
    echo "Running post-deploy command..."
    if eval "$POST_DEPLOY_CMD"; then
        echo "Post-deploy command succeeded."
    else
        echo "WARNING: Post-deploy command failed (exit code $?)."
    fi
fi

# ── Health check ─────────────────────────────────────────────
if [ -n "$PRODUCTION_URL" ]; then
    echo ""
    echo "Running health check..."
    HTTP_RESULT=$(curl -s -o /dev/null -w "%{http_code} %{time_total}" -L "$PRODUCTION_URL" 2>/dev/null || echo "000 0")
    HTTP_CODE=$(echo "$HTTP_RESULT" | awk '{print $1}')
    HTTP_TIME=$(echo "$HTTP_RESULT" | awk '{print $2}')

    if [ "$HTTP_CODE" = "200" ]; then
        echo "Health check: $HTTP_CODE OK (${HTTP_TIME}s)"
    elif [ "$HTTP_CODE" = "000" ]; then
        echo "WARNING: Health check failed — could not connect to $PRODUCTION_URL"
    else
        echo "WARNING: Health check returned HTTP $HTTP_CODE — verify site manually at $PRODUCTION_URL"
    fi
fi

echo ""
echo "Done."
