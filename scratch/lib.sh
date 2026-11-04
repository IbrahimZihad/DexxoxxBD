#!/usr/bin/env bash
# Test-only helpers for live verification. Deleted after the run.
BASE="http://127.0.0.1:8123"
PHP="F:/Xamp/php/php.exe"
SCRATCH="F:/Xamp/htdocs/DexxoxxBD/scratch"
MYSQL="F:/Xamp/mysql/bin/mysql.exe"
TMP="$SCRATCH/tmp"
mkdir -p "$TMP"
declare -A CSRF_CACHE

Q() {
  "$MYSQL" -h 127.0.0.1 -P 3307 -u root -D dexxoxxbd -N -B -e "$1" 2>&1 | grep -v '^Warning' || true
}

login() { # login <jar> <email> <password>
  local jar="$TMP/$1.jar"
  rm -f "$jar"
  local t
  t=$(curl -s -c "$jar" "$BASE/api/auth.php?action=token" | "$PHP" -r '$j=stream_get_contents(STDIN);$d=json_decode($j,true);echo $d["csrf_token"] ?? "";')
  curl -s -b "$jar" -c "$jar" -X POST "$BASE/api/auth.php?action=login" \
    -H "Content-Type: application/json" -H "X-CSRF-Token: $t" \
    -d "{\"email\":\"$2\",\"password\":\"$3\"}" > "$TMP/login-$1.json"
  CSRF_CACHE["$1"]=$(curl -s -b "$jar" -c "$jar" "$BASE/api/auth.php?action=token" | "$PHP" -r '$j=stream_get_contents(STDIN);$d=json_decode($j,true);echo $d["csrf_token"] ?? "";')
}

csrf() { printf '%s' "${CSRF_CACHE[$1]:-}"; }

req() { # req <jar> <METHOD> <path> <json>
  local jar="$TMP/$1.jar" m="$2" path="$3" body="${4:-}"
  local out="$TMP/resp.txt"
  local args=(-s -o "$out" -w '%{http_code}' -b "$jar" -c "$jar" -X "$m" "$BASE$path")
  case "$m" in
    GET|HEAD) ;;
    *) args+=(-H "X-CSRF-Token: $(csrf "$1")");;
  esac
  if [ -n "$body" ]; then
    args+=(-H 'Content-Type: application/json' --data-binary "$body")
  fi
  local code
  code=$(curl "${args[@]}" 2>&1)
  echo "[$m $path] -> $code"
  cat "$out"
  echo
}

get() { # get <jar-or-'-'> <path>
  local jar="$1" path="$2"
  local opts=()
  if [ "$jar" != "-" ]; then opts=(-b "$TMP/$jar.jar" -c "$TMP/$jar.jar"); fi
  local code
  code=$(curl -s -o "$TMP/resp.txt" -w '%{http_code}' "${opts[@]}" "$BASE$path")
  echo "[GET $path] -> $code"
  cat "$TMP/resp.txt"
  echo
}
