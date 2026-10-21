#!/bin/bash
# +----------------------------------------------------------------------
# | 内网自动同步脚本（运行在内网服务器，以 li 用户执行）
# | 原理：轮询腾讯云站点当前提交号，有变化时从腾讯云站点目录 rsync 代码
# | 仅同步可写目录 app/route/bin/database/public，保留 runtime、config、vendor、.env
# +----------------------------------------------------------------------

SITE=/var/www/classroom
SRCROOT=/www/wwwroot/43.160.248.111
TENCENT=root@43.160.248.111
STAMP=$HOME/.classroom_sync_sha
SSH_OPT="-o BatchMode=yes -o ConnectTimeout=8"

latest=$(ssh $SSH_OPT "$TENCENT" "cd $SRCROOT && git rev-parse HEAD" 2>/dev/null) || exit 0
[ -n "$latest" ] || exit 0
current=$(cat "$STAMP" 2>/dev/null)
[ "$latest" = "$current" ] && exit 0

set -e
for d in app route bin database; do
    rsync -r --no-perms --no-times --delete -e "ssh $SSH_OPT" "$TENCENT:$SRCROOT/$d/" "$SITE/$d/"
done
# assets 目录在内网属于 www-data 且不可写，保持内网原样
rsync -r --no-perms --no-times --delete --exclude 'assets/' --exclude 'uploads/' \
    -e "ssh $SSH_OPT" "$TENCENT:$SRCROOT/public/" "$SITE/public/"

echo "$latest" > "$STAMP"
echo "$(date '+%F %T') synced ${current:-none} -> $latest"
