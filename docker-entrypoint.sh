#!/bin/bash
set -e

CONFIG_FILE="/app/config/settings.php"
DB_DIR="/db"

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $*"
}

debug_log() {
    if [ "${DEBUG:-false}" = "true" ]; then
        echo "[$(date +'%Y-%m-%d %H:%M:%S')] DEBUG: $*"
    fi
}

# Process Docker secrets - converts *__FILE environment variables to regular variables
process_secret_files() {
    for VAR_NAME in $(env | grep '^[^=]\+__FILE=.\+' | sed -r 's/^([^=]*)__FILE=.*/\1/g'); do
        VAR_NAME_FILE="${VAR_NAME}__FILE"

        # Check if both regular and __FILE versions are set (they are exclusive)
        [ "${!VAR_NAME}" ] && {
            log "ERROR: Both ${VAR_NAME} and ${VAR_NAME_FILE} are set but are exclusive"
            exit 1
        }

        VAR_FILENAME="${!VAR_NAME_FILE}"
        log "Getting secret ${VAR_NAME} from ${VAR_FILENAME}"

        # Validate the secret file exists and is readable
        [ ! -r "${VAR_FILENAME}" ] && {
            log "ERROR: ${VAR_FILENAME} does not exist or is not readable"
            exit 1
        }

        # Read the secret file content and export as environment variable
        export "${VAR_NAME}"="$(<"${VAR_FILENAME}")"
        unset "${VAR_NAME_FILE}"
    done
}

# Initialize SQLite database if it doesn't exist
init_sqlite_db() {
    if [ "${DB_TYPE}" = "sqlite" ] && [ ! -f "${DB_FILE:-/db/pdns.db}" ]; then
        local db_file="${DB_FILE:-/db/pdns.db}"
        log "Initializing SQLite database at ${db_file}..."

        # Create database directory if it doesn't exist
        mkdir -p "$(dirname "${db_file}")"

        # Initialize PowerDNS schema
        if [ -f "/app/sql/pdns/47/schema.sqlite3.sql" ]; then
            sqlite3 "${db_file}" < /app/sql/pdns/47/schema.sqlite3.sql
        else
            log "WARNING: PowerDNS schema file not found, database may not be properly initialized"
        fi

        # Initialize Poweradmin schema
        if [ -f "/app/sql/poweradmin-sqlite-db-structure.sql" ]; then
            sqlite3 "${db_file}" < /app/sql/poweradmin-sqlite-db-structure.sql
        else
            log "WARNING: Poweradmin schema file not found, database may not be properly initialized"
        fi

        log "SQLite database initialized successfully"
    fi
}

# Validate required database configuration
validate_database_config() {
    debug_log "Starting database validation with DB_TYPE=${DB_TYPE:-sqlite}"
    case "${DB_TYPE:-sqlite}" in
        "sqlite")
            local db_file="${DB_FILE:-/db/pdns.db}"
            debug_log "Checking SQLite database file: ${db_file}"
            debug_log "File exists check: [ -f ${db_file} ] = $([ -f "${db_file}" ] && echo true || echo false)"
            debug_log "Directory writable check: [ -w $(dirname ${db_file}) ] = $([ -w "$(dirname "${db_file}")" ] && echo true || echo false)"
            [ ! -f "${db_file}" ] && [ ! -w "$(dirname "${db_file}")" ] && {
                log "ERROR: SQLite database file ${db_file} doesn't exist and directory is not writable"
                exit 1
            }
            debug_log "SQLite validation passed"
            ;;
        "mysql"|"pgsql")
            [ -z "${DB_HOST}" ] && {
                log "ERROR: DB_HOST is required for ${DB_TYPE} database"
                exit 1
            }
            [ -z "${DB_USER}" ] && {
                log "ERROR: DB_USER is required for ${DB_TYPE} database"
                exit 1
            }
            [ -z "${DB_NAME}" ] && {
                log "ERROR: DB_NAME is required for ${DB_TYPE} database"
                exit 1
            }
            ;;
        *)
            log "ERROR: Unsupported database type: ${DB_TYPE}. Supported types: sqlite, mysql, pgsql"
            exit 1
            ;;
    esac
    debug_log "Database validation function completed"
}

# Validate DNS configuration
validate_dns_config() {
    debug_log "DNS_NS1='${DNS_NS1}'"
    debug_log "DNS_NS2='${DNS_NS2}'"
    debug_log "DNS_HOSTMASTER='${DNS_HOSTMASTER}'"
    [ -z "${DNS_NS1}" ] && {
        log "ERROR: DNS_NS1 (primary nameserver) is required"
        exit 1
    }
    [ -z "${DNS_NS2}" ] && {
        log "ERROR: DNS_NS2 (secondary nameserver) is required"
        exit 1
    }
    [ -z "${DNS_HOSTMASTER}" ] && {
        log "ERROR: DNS_HOSTMASTER is required"
        exit 1
    }
    debug_log "DNS validation completed"
}

# Validate mail configuration if enabled
validate_mail_config() {
    local mail_enabled=$(echo "${PA_MAIL_ENABLED:-true}" | tr '[:upper:]' '[:lower:]')
    if [ "$mail_enabled" = "true" ] && [ "${PA_MAIL_TRANSPORT}" = "smtp" ]; then
        [ -z "${PA_SMTP_HOST}" ] && {
            log "ERROR: PA_SMTP_HOST is required when using SMTP transport"
            exit 1
        }
        [ -z "${PA_MAIL_FROM}" ] && {
            log "ERROR: PA_MAIL_FROM is required when mail is enabled"
            exit 1
        }
    fi
}

# Validate API configuration if enabled
validate_api_config() {
    local api_enabled=$(echo "${PA_API_ENABLED:-false}" | tr '[:upper:]' '[:lower:]')
    if [ "$api_enabled" = "true" ] && [ -n "${PA_PDNS_API_URL}" ]; then
        [ -z "${PA_PDNS_API_KEY}" ] && {
            log "ERROR: PA_PDNS_API_KEY is required when PowerDNS API URL is specified"
            exit 1
        }
    fi
}

# Validate LDAP configuration if enabled
validate_ldap_config() {
    local ldap_enabled=$(echo "${PA_LDAP_ENABLED:-false}" | tr '[:upper:]' '[:lower:]')
    if [ "$ldap_enabled" = "true" ]; then
        local required_ldap_vars=("PA_LDAP_URI" "PA_LDAP_BASE_DN")
        for var in "${required_ldap_vars[@]}"; do
            [ -z "${!var}" ] && {
                log "ERROR: ${var} is required when LDAP is enabled"
                exit 1
            }
        done
    fi
}

# Create initial admin user if specified
create_admin_user() {
    local create_admin=$(echo "${PA_CREATE_ADMIN:-false}" | tr '[:upper:]' '[:lower:]')

    if [ "$create_admin" != "true" ] && [ "$create_admin" != "1" ] && [ "$create_admin" != "yes" ]; then
        debug_log "Admin user creation disabled"
        return 0
    fi

    local admin_username="${PA_ADMIN_USERNAME:-admin}"
    local admin_password="${PA_ADMIN_PASSWORD:-admin}"
    local admin_email="${PA_ADMIN_EMAIL:-admin@example.com}"
    local admin_fullname="${PA_ADMIN_FULLNAME:-Administrator}"

    debug_log "Creating admin user: ${admin_username}"

    # Generate password hash using PHP
    local password_hash
    local temp_php_file="/tmp/hash_password.php"
    echo "<?php echo password_hash('${admin_password}', PASSWORD_DEFAULT);" > "${temp_php_file}"
    password_hash=$(php -d error_reporting=0 -d display_startup_errors=0 "${temp_php_file}" 2>/dev/null)
    rm -f "${temp_php_file}"

    if [ $? -ne 0 ] || [ -z "${password_hash}" ]; then
        log "ERROR: Failed to generate password hash for admin user"
        exit 1
    fi

    debug_log "Generated password hash for admin user"

    # Database-specific user creation
    case "${DB_TYPE:-sqlite}" in
        "sqlite")
            local db_file="${DB_FILE:-/db/pdns.db}"
            debug_log "Creating admin user in SQLite database: ${db_file}"

            # Check if user already exists
            local user_exists
            user_exists=$(sqlite3 "${db_file}" "SELECT COUNT(*) FROM users WHERE username='${admin_username}';")

            if [ "${user_exists}" -gt 0 ]; then
                log "Admin user '${admin_username}' already exists, skipping creation"
                return 0
            fi

            # Insert admin user
            sqlite3 "${db_file}" "INSERT INTO users (username, password, fullname, email, description, perm_templ, active, use_ldap) VALUES ('${admin_username}', '${password_hash}', '${admin_fullname}', '${admin_email}', 'System Administrator', 1, 1, 0);"
            ;;

        "mysql")
            debug_log "Creating admin user in MySQL database"

            # Check if user already exists
            local user_exists
            user_exists=$(mysql -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -sNe "SELECT COUNT(*) FROM users WHERE username='${admin_username}';")

            if [ "${user_exists}" -gt 0 ]; then
                log "Admin user '${admin_username}' already exists, skipping creation"
                return 0
            fi

            # Insert admin user
            mysql -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "INSERT INTO users (username, password, fullname, email, description, perm_templ, active, use_ldap) VALUES ('${admin_username}', '${password_hash}', '${admin_fullname}', '${admin_email}', 'System Administrator', 1, 1, 0);"
            ;;

        "pgsql")
            debug_log "Creating admin user in PostgreSQL database"

            # Check if user already exists
            local user_exists
            user_exists=$(PGPASSWORD="${DB_PASS}" psql -h "${DB_HOST}" -U "${DB_USER}" -d "${DB_NAME}" -tAc "SELECT COUNT(*) FROM users WHERE username='${admin_username}';")

            if [ "${user_exists}" -gt 0 ]; then
                log "Admin user '${admin_username}' already exists, skipping creation"
                return 0
            fi

            # Insert admin user
            PGPASSWORD="${DB_PASS}" psql -h "${DB_HOST}" -U "${DB_USER}" -d "${DB_NAME}" -c "INSERT INTO users (username, password, fullname, email, description, perm_templ, active, use_ldap) VALUES ('${admin_username}', '${password_hash}', '${admin_fullname}', '${admin_email}', 'System Administrator', 1, 1, 0);"
            ;;
    esac

    if [ $? -eq 0 ]; then
        log "Admin user '${admin_username}' created successfully"
    else
        log "ERROR: Failed to create admin user '${admin_username}'"
        exit 1
    fi
}

# Generate configuration file from environment variables
generate_config() {
    log "Generating configuration from environment variables..."

    # Generate a random session key if not provided
    local session_key="${PA_SESSION_KEY:-$(openssl rand -hex 32)}"

    # Convert boolean values to lowercase
    local recaptcha_enabled=$(echo "${PA_RECAPTCHA_ENABLED:-false}" | tr '[:upper:]' '[:lower:]')
    local mail_enabled=$(echo "${PA_MAIL_ENABLED:-true}" | tr '[:upper:]' '[:lower:]')
    local api_enabled=$(echo "${PA_API_ENABLED:-false}" | tr '[:upper:]' '[:lower:]')
    local api_basic_auth_enabled=$(echo "${PA_API_BASIC_AUTH_ENABLED:-false}" | tr '[:upper:]' '[:lower:]')
    local api_docs_enabled=$(echo "${PA_API_DOCS_ENABLED:-false}" | tr '[:upper:]' '[:lower:]')
    local ldap_enabled=$(echo "${PA_LDAP_ENABLED:-false}" | tr '[:upper:]' '[:lower:]')

    cat > "${CONFIG_FILE}" << EOF
<?php

return [
    'database' => [
        'type' => '${DB_TYPE:-sqlite}',
        'host' => '${DB_HOST:-}',
        'user' => '${DB_USER:-}',
        'password' => '${DB_PASS:-}',
        'name' => '${DB_NAME:-}',
        'file' => '${DB_FILE:-/db/pdns.db}',
    ],
    'dns' => [
        'hostmaster' => '${DNS_HOSTMASTER:-hostmaster.example.com}',
        'ns1' => '${DNS_NS1:-ns1.example.com}',
        'ns2' => '${DNS_NS2:-ns2.example.com}',
        'ns3' => '${DNS_NS3:-}',
        'ns4' => '${DNS_NS4:-}',
    ],
    'security' => [
        'session_key' => '${session_key}',
        'recaptcha' => [
            'enabled' => ${recaptcha_enabled},
            'site_key' => '${PA_RECAPTCHA_SITE_KEY:-}',
            'secret_key' => '${PA_RECAPTCHA_SECRET_KEY:-}',
        ],
    ],
    'mail' => [
        'enabled' => ${mail_enabled},
        'transport' => '${PA_MAIL_TRANSPORT:-php}',
        'host' => '${PA_SMTP_HOST:-}',
        'port' => ${PA_SMTP_PORT:-587},
        'username' => '${PA_SMTP_USER:-}',
        'password' => '${PA_SMTP_PASSWORD:-}',
        'encryption' => '${PA_SMTP_ENCRYPTION:-tls}',
        'from' => '${PA_MAIL_FROM:-}',
        'from_name' => '${PA_MAIL_FROM_NAME:-}',
    ],
    'interface' => [
        'title' => '${PA_APP_TITLE:-Poweradmin}',
        'language' => '${PA_DEFAULT_LANGUAGE:-en_EN}',
    ],
    'api' => [
        'enabled' => ${api_enabled},
        'basic_auth_enabled' => ${api_basic_auth_enabled},
        'docs_enabled' => ${api_docs_enabled},
    ],
    'pdns_api' => [
        'url' => '${PA_PDNS_API_URL:-}',
        'key' => '${PA_PDNS_API_KEY:-}',
        'server_name' => '${PA_PDNS_SERVER_NAME:-localhost}',
    ],
    'ldap' => [
        'enabled' => ${ldap_enabled},
        'uri' => '${PA_LDAP_URI:-}',
        'base_dn' => '${PA_LDAP_BASE_DN:-}',
        'bind_dn' => '${PA_LDAP_BIND_DN:-}',
        'bind_password' => '${PA_LDAP_BIND_PASSWORD:-}',
    ],
    'misc' => [
        'timezone' => '${PA_TIMEZONE:-UTC}',
    ],
];
EOF

    # Set proper permissions
    chmod 644 "${CONFIG_FILE}"
    chown www-data:www-data "${CONFIG_FILE}"

    log "Configuration file generated successfully"
}

# Print configuration summary (with redacted secrets)
print_config_summary() {
    log "=== Poweradmin Configuration Summary ==="
    log "Database Type: ${DB_TYPE:-sqlite}"
    if [ "${DB_TYPE:-sqlite}" != "sqlite" ]; then
        log "Database Host: ${DB_HOST:-}"
        log "Database Name: ${DB_NAME:-}"
        log "Database User: ${DB_USER:-}"
    else
        log "Database File: ${DB_FILE:-/db/pdns.db}"
    fi
    log "DNS NS1: ${DNS_NS1:-ns1.example.com}"
    log "DNS NS2: ${DNS_NS2:-ns2.example.com}"
    log "DNS Hostmaster: ${DNS_HOSTMASTER:-hostmaster.example.com}"
    log "App Title: ${PA_APP_TITLE:-Poweradmin}"
    log "Default Language: ${PA_DEFAULT_LANGUAGE:-en_EN}"
    log "Mail Enabled: ${PA_MAIL_ENABLED:-true}"
    log "API Enabled: ${PA_API_ENABLED:-false}"
    log "LDAP Enabled: ${PA_LDAP_ENABLED:-false}"
    log "Admin User Creation: ${PA_CREATE_ADMIN:-false}"
    if [ "${PA_CREATE_ADMIN:-false}" = "true" ]; then
        log "Admin Username: ${PA_ADMIN_USERNAME:-admin}"
        log "Admin Email: ${PA_ADMIN_EMAIL:-admin@example.com}"
    fi
    log "Timezone: ${PA_TIMEZONE:-UTC}"
    log "======================================="
}

# Set up proper file permissions
setup_permissions() {
    log "Setting up file permissions..."

    # Ensure directories exist and have proper permissions
    mkdir -p /app/config "${DB_DIR}"

    # Set ownership
    chown -R www-data:www-data /app "${DB_DIR}"

    # Set permissions
    chmod -R 755 /app "${DB_DIR}"

    log "File permissions set successfully"
}

main() {
    log "Poweradmin Docker Container Starting..."

    # Configuration Priority:
    # 1. PA_CONFIG_PATH (custom config file) - highest priority
    # 2. Individual environment variables (with Docker secrets support) - fallback

    # Process Docker secrets first
    log "Processing Docker secrets..."
    process_secret_files

    if [ -n "${PA_CONFIG_PATH}" ] && [ -f "${PA_CONFIG_PATH}" ]; then
        log "Using custom configuration from: ${PA_CONFIG_PATH}"
        cp "${PA_CONFIG_PATH}" "${CONFIG_FILE}"
        chmod 644 "${CONFIG_FILE}"
        chown www-data:www-data "${CONFIG_FILE}"
    elif [ -f "${CONFIG_FILE}" ]; then
        log "Using existing settings.php (generated from environment variables)"
    else
        log "No custom config found. Generating settings.php from environment variables..."

        # Initialize database if needed (before validation)
        init_sqlite_db

        # Validate all configurations
        debug_log "Starting configuration validation..."
        validate_database_config
        debug_log "Database validation completed successfully"
        validate_dns_config
        debug_log "DNS validation completed successfully"
        validate_mail_config
        debug_log "Mail validation completed successfully"
        validate_api_config
        debug_log "API validation completed successfully"
        validate_ldap_config
        debug_log "LDAP validation completed successfully"
        log "Configuration validation completed successfully"

        # Generate configuration
        generate_config
    fi

    # Create admin user if requested (after database and config are ready)
    create_admin_user

    # Setup file permissions
    setup_permissions

    # Print configuration summary
    print_config_summary

    log "Configuration loaded successfully"
    log "Starting Poweradmin..."

    # Execute the command
    exec "$@"
}

# Run main function with all arguments
main "$@"
