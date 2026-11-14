#!/usr/bin/env bash
# Idempotent provisioning for the Valgamaa Äriklubi WordPress site.
#
# Runs in the docker `wpcli` container (docker compose run --rm wpcli bash /scripts/setup.sh),
# on Zone over SSH, and in Git Bash on Windows. Safe to re-run: nothing is duplicated.
#
# Environment:
#   SITE_URL, ADMIN_USER, ADMIN_EMAIL, ADMIN_PASSWORD   required for the first install
#   SITE_TITLE            title used by `wp core install` (the importer sets the real one)
#   WP_CLI                WP-CLI command, default "wp" (e.g. "php /path/to/wp-cli.phar")
#   WP_PATH               WordPress root, passed as --path (default: WP-CLI's own lookup)
#   CONTENT_DIR           default <repo>/content
#   BRAND_DIR             default <repo>/brand
#   WP_ENVIRONMENT_TYPE   overrides the environment detected from WordPress
#   DB_NAME, DB_USER, DB_PASSWORD, DB_HOST (default localhost), DB_PREFIX (default wp_)
#                         only used to create wp-config.php when it does not exist
#   DISABLE_WP_CRON=1     write DISABLE_WP_CRON=true to wp-config.php (server cron instead)
#   AK_FORCE_UPDATE=1     importer overwrites items that were edited in wp-admin
#   AK_RECREATE_DELETED=1 importer re-creates items that were deleted in wp-admin
set -euo pipefail

# Git Bash: keep MSYS from rewriting arguments such as '/%postname%/' into Windows paths.
# Paths are converted explicitly with to_native() instead.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"

log()  { printf '\n==> %s\n' "$*"; }
info() { printf '    %s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*" >&2; }
die()  { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

# Windows-native PHP cannot read /c/... paths; cygpath only exists in Git Bash/Cygwin.
to_native() {
  if command -v cygpath >/dev/null 2>&1; then cygpath -m "$1"; else printf '%s\n' "$1"; fi
}

WP_CLI="${WP_CLI:-wp}"
WP_ARGS=()
if [ -n "${WP_PATH:-}" ]; then
  WP_ARGS+=("--path=$(to_native "$WP_PATH")")
fi
if [ "$(id -u 2>/dev/null || echo 1000)" = "0" ]; then
  WP_ARGS+=("--allow-root")
fi

# $WP_CLI is intentionally unquoted so "php /path/wp-cli.phar" splits into words.
wp_() { $WP_CLI ${WP_ARGS[@]+"${WP_ARGS[@]}"} "$@"; }
# Captured output without stderr and without Windows CRs.
wp_out() { wp_ "$@" 2>/dev/null | tr -d '\r'; }
try_() { "$@" || warn "failed (continuing): ${*}"; }

CONTENT_DIR="${CONTENT_DIR:-${REPO_DIR%/}/content}"
BRAND_DIR="${BRAND_DIR:-${REPO_DIR%/}/brand}"
SITE_TITLE="${SITE_TITLE:-Valgamaa Äriklubi}"

[ -d "$CONTENT_DIR" ] || die "Content directory not found: $CONTENT_DIR (set CONTENT_DIR)"
[ -d "$BRAND_DIR" ] || warn "Brand directory not found: $BRAND_DIR (logo and site icon will be skipped)"

# ---------------------------------------------------------------------------
log "Checking WP-CLI ($WP_CLI)"
wp_ cli version >/dev/null 2>&1 || die "WP-CLI is not runnable as '$WP_CLI'. Set WP_CLI, e.g. WP_CLI='php /path/to/wp-cli.phar'."
info "$(wp_out cli version)"

# ---------------------------------------------------------------------------
log "WordPress core files"
if ! wp_ core version >/dev/null 2>&1; then
  if [ -z "${WP_PATH:-}" ] && [ "$(pwd -P)" = "$(cd "$REPO_DIR" && pwd -P)" ]; then
    die "No WordPress found. Set WP_PATH or cd into the web root; refusing to download WordPress into the repository."
  fi
  info "Not found, downloading WordPress (et)"
  wp_ core download --locale=et
fi
info "WordPress $(wp_out core version)"

# ---------------------------------------------------------------------------
log "wp-config.php"
if ! wp_ config path >/dev/null 2>&1; then
  [ -n "${DB_NAME:-}" ] && [ -n "${DB_USER:-}" ] && [ -n "${DB_PASSWORD:-}" ] \
    || die "wp-config.php is missing. Provide DB_NAME, DB_USER, DB_PASSWORD (and DB_HOST) to create it."
  wp_ config create \
    --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" \
    --dbhost="${DB_HOST:-localhost}" --dbprefix="${DB_PREFIX:-wp_}" \
    --locale=et --skip-check
  wp_ config set WP_ENVIRONMENT_TYPE "${WP_ENVIRONMENT_TYPE:-production}" --type=constant
  wp_ config set DISALLOW_FILE_EDIT true --raw --type=constant
  wp_ config set WP_AUTO_UPDATE_CORE true --raw --type=constant
  info "Created $(wp_out config path)"
else
  info "Using $(wp_out config path)"
fi
if [ "${DISABLE_WP_CRON:-}" = "1" ]; then
  try_ wp_ config set DISABLE_WP_CRON true --raw --type=constant
fi

# ---------------------------------------------------------------------------
log "WordPress installation"
if wp_ core is-installed >/dev/null 2>&1; then
  info "Already installed"
else
  for v in SITE_URL ADMIN_USER ADMIN_EMAIL ADMIN_PASSWORD; do
    [ -n "${!v:-}" ] || die "$v is required for the first install."
  done
  wp_ core install \
    --url="$SITE_URL" --title="$SITE_TITLE" \
    --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASSWORD" \
    --admin_email="$ADMIN_EMAIL" --skip-email
  wp_ option update ak_core_installed_by_setup 1 --autoload=no >/dev/null
fi
# WP-CLI on Windows can guess a wrong siteurl during install, so always enforce SITE_URL.
if [ -n "${SITE_URL:-}" ]; then
  SITE_URL="${SITE_URL%/}"
  for opt in home siteurl; do
    if [ "$(wp_out option get "$opt" || true)" != "$SITE_URL" ]; then
      info "Setting $opt to $SITE_URL"
      wp_ option update "$opt" "$SITE_URL" >/dev/null
    fi
  done
fi

# Remove the stock "Hello world", sample page, privacy draft and comment, but only
# on a site this script installed and only before the first successful run.
if [ "$(wp_out option get ak_setup_initialized || true)" != "1" ] \
   && [ "$(wp_out option get ak_core_installed_by_setup || true)" = "1" ]; then
  log "Removing default content"
  for id in 1 2 3; do
    if wp_ post exists "$id" >/dev/null 2>&1 && [ -z "$(wp_out post meta get "$id" _ak_key || true)" ]; then
      wp_ post delete "$id" --force >/dev/null && info "Deleted default post #$id"
    fi
  done
  wp_ comment delete 1 --force >/dev/null 2>&1 || true
fi

# ---------------------------------------------------------------------------
log "Language (et)"
try_ wp_ language core install et
try_ wp_ site switch-language et
try_ wp_ language core update

# ---------------------------------------------------------------------------
log "General settings"
wp_ option update timezone_string 'Europe/Tallinn' >/dev/null
wp_ option update date_format 'd.m.Y' >/dev/null
wp_ option update time_format 'H:i' >/dev/null
wp_ option update start_of_week 1 >/dev/null
wp_ option update default_comment_status closed >/dev/null
wp_ option update default_ping_status closed >/dev/null
wp_ option update default_pingback_flag 0 >/dev/null

ENV_TYPE="${WP_ENVIRONMENT_TYPE:-}"
if [ -z "$ENV_TYPE" ]; then
  ENV_TYPE="$(wp_out eval 'echo wp_get_environment_type();' || true)"
fi
ENV_TYPE="${ENV_TYPE:-production}"
if [ "$ENV_TYPE" = "production" ]; then
  wp_ option update blog_public 1 >/dev/null
  info "Environment: production (search engines allowed)"
else
  wp_ option update blog_public 0 >/dev/null
  info "Environment: $ENV_TYPE (search engines discouraged)"
fi

# ---------------------------------------------------------------------------
log "Plugins"
for p in polylang limit-login-attempts-reloaded; do
  if ! wp_ plugin is-installed "$p" >/dev/null 2>&1; then
    wp_ plugin install "$p"
  fi
  wp_ plugin activate "$p"
done
# Only the plugins listed in PLAN.md stay installed.
for p in akismet hello; do
  if wp_ plugin is-installed "$p" >/dev/null 2>&1 && ! wp_ plugin is-active "$p" >/dev/null 2>&1; then
    try_ wp_ plugin delete "$p"
  fi
done

log "Themes"
if ! wp_ theme is-installed generatepress >/dev/null 2>&1; then
  wp_ theme install generatepress
fi
wp_ theme is-installed ariklubi >/dev/null 2>&1 \
  || die "Child theme 'ariklubi' is missing from wp-content/themes (Docker bind-mounts ./wp-content/themes/ariklubi; on Zone upload it first)."
if [ "$(wp_out option get stylesheet || true)" != "ariklubi" ]; then
  wp_ theme activate ariklubi
fi
info "Active theme: ariklubi"

log "Translations for plugins and themes"
try_ wp_ language plugin install --all et
try_ wp_ language theme install --all et
try_ wp_ language plugin update --all
try_ wp_ language theme update --all

log "Auto-updates"
wp_ plugin auto-updates enable --all >/dev/null 2>&1 || info "plugin auto-updates already enabled"
wp_ theme auto-updates enable --all >/dev/null 2>&1 || info "theme auto-updates already enabled"

log "Permalinks"
wp_ rewrite structure '/%postname%/' --hard >/dev/null 2>&1 || wp_ rewrite structure '/%postname%/'

# ---------------------------------------------------------------------------
CONTENT_DIR="$(to_native "$CONTENT_DIR")"
BRAND_DIR="$(to_native "$BRAND_DIR")"
AK_SCRIPTS_DIR="$(to_native "$SCRIPT_DIR")"
AK_FORCE_UPDATE="${AK_FORCE_UPDATE:-}"
AK_RECREATE_DELETED="${AK_RECREATE_DELETED:-}"
export CONTENT_DIR BRAND_DIR AK_SCRIPTS_DIR AK_FORCE_UPDATE AK_RECREATE_DELETED

log "Configuring Polylang"
wp_ eval-file "$AK_SCRIPTS_DIR/configure-polylang.php"

log "Importing content from $CONTENT_DIR"
wp_ eval-file "$AK_SCRIPTS_DIR/import-content.php"

log "Flushing rewrite rules"
wp_ rewrite flush --hard >/dev/null 2>&1 || wp_ rewrite flush
try_ wp_ cache flush
if [ "$(wp_out option get ak_setup_initialized || true)" != "1" ]; then
  wp_ option update ak_setup_initialized 1 --autoload=no >/dev/null
fi

# ---------------------------------------------------------------------------
log "Summary"
info "Site:   $(wp_out option get home || true)"
info "Admin:  $(wp_out option get home || true)/wp-admin/  (user: ${ADMIN_USER:-see wp user list})"
info "Env:    $ENV_TYPE, blog_public=$(wp_out option get blog_public || true)"
for t in page post ak_member ak_album wp_block nav_menu_item; do
  info "$(printf '%-14s %s' "$t" "$(wp_out post list --post_type="$t" --post_status=any --format=count || echo '?')")"
done
echo
wp_ post list --post_type=page --post_status=any --fields=ID,post_title,post_status,url --format=table || true
echo
info "Uploads: the web server needs upload_max_filesize and post_max_size >= 64M and memory_limit >= 256M"
info "(set in the Zone panel; Docker uses scripts/docker/php.ini). Large images are scaled to 2560 px (WordPress default)."
log "Done"
