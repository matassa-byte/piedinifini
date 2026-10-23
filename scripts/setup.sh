#!/bin/sh
# Prima installazione di WordPress + WooCommerce. Si può rilanciare: salta quello che è già fatto.
# Uso: ./scripts/wp.sh setup (vedi README)
set -eu

if ! wp core is-installed 2>/dev/null; then
  wp core install \
    --url="$WP_URL" \
    --title="Piedinifini" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

wp language core install it_IT --activate
wp option update timezone_string "Europe/Rome"
wp option update date_format "d/m/Y"
wp rewrite structure "/%postname%/" --hard

if ! wp plugin is-installed woocommerce; then
  wp plugin install woocommerce --version="$WOOCOMMERCE_VERSION"
fi
wp plugin activate woocommerce
wp language plugin install woocommerce it_IT

# Negozio in Italia, prezzi in euro con formato italiano
wp option update woocommerce_default_country "IT:GE"
wp option update woocommerce_currency "EUR"
wp option update woocommerce_currency_pos "right_space"
wp option update woocommerce_price_thousand_sep "."
wp option update woocommerce_price_decimal_sep ","
wp option update woocommerce_price_num_decimals 2
wp option update woocommerce_weight_unit "kg"
wp option update woocommerce_dimension_unit "cm"
wp option update woocommerce_calc_taxes "yes"
wp option update woocommerce_prices_include_tax "yes"
wp option update woocommerce_manage_stock "yes"
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json
wp wc tool run install_pages --user="$WP_ADMIN_USER"

# WooCommerce crea le pagine in inglese: titoli e indirizzi in italiano
rinomina_pagina() {
  id=$(wp option get "$1" 2>/dev/null || true)
  [ -n "$id" ] && [ "$id" != "0" ] && wp post update "$id" --post_title="$2" --post_name="$3"
}
rinomina_pagina woocommerce_shop_page_id "Negozio" "negozio"
rinomina_pagina woocommerce_cart_page_id "Carrello" "carrello"
rinomina_pagina woocommerce_checkout_page_id "Cassa" "cassa"
rinomina_pagina woocommerce_myaccount_page_id "Il mio account" "mio-account"
rinomina_pagina woocommerce_refund_returns_page_id "Rimborsi e resi" "rimborsi-e-resi"
rinomina_pagina wp_page_for_privacy_policy "Informativa sulla privacy" "privacy"

# Il tema piedinifini è un child theme: serve il tema padre
wp theme is-installed twentytwentyfive || wp theme install twentytwentyfive
wp language theme install twentytwentyfive it_IT
wp theme activate piedinifini
wp plugin activate piedinifini-magazzino

# Pulizia dei contenuti di esempio di WordPress
wp post delete 1 --force 2>/dev/null || true
wp post delete 2 --force 2>/dev/null || true
wp plugin delete hello akismet 2>/dev/null || true

echo "Fatto: apri $WP_URL"
