<?php
/**
 * Plugin Name: Piedinifini Magazzino
 * Description: Sincronizza giacenze e ordini tra WooCommerce e il gestionale di magazzino.
 * Version: 0.1.0
 * Author: Fancy Factory
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * Text Domain: piedinifini-magazzino
 *
 * BOZZA: il formato dei dati è provvisorio, va concordato con Federico (gestionale).
 *
 * Gestionale → shop: POST /wp-json/piedinifini/v1/giacenze
 *   Header: X-Piedinifini-Token: <PIEDINIFINI_MAGAZZINO_TOKEN>
 *   Body:   {"articoli": [{"sku": "ABC-32", "quantita": 4}, ...]}
 *
 * Shop → gestionale: a ogni cambio di stato di un ordine, POST JSON a PIEDINIFINI_MAGAZZINO_URL
 *   con lo stesso header. L'invio passa da Action Scheduler, che ritenta e tiene il registro
 *   (WooCommerce → Stato → Azioni programmate).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

function piedinifini_magazzino_token(): string {
	return defined( 'PIEDINIFINI_MAGAZZINO_TOKEN' ) ? (string) PIEDINIFINI_MAGAZZINO_TOKEN : '';
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'piedinifini/v1', '/giacenze', array(
		'methods'             => 'POST',
		'callback'            => 'piedinifini_magazzino_aggiorna_giacenze',
		'permission_callback' => function ( WP_REST_Request $request ) {
			$token = piedinifini_magazzino_token();
			return '' !== $token && hash_equals( $token, (string) $request->get_header( 'X-Piedinifini-Token' ) );
		},
	) );
} );

function piedinifini_magazzino_aggiorna_giacenze( WP_REST_Request $request ) {
	$articoli = $request->get_json_params()['articoli'] ?? null;
	if ( ! is_array( $articoli ) ) {
		return new WP_Error( 'piedinifini_dati', 'Manca la lista "articoli".', array( 'status' => 400 ) );
	}

	$aggiornati = array();
	$non_trovati = array();
	foreach ( $articoli as $articolo ) {
		$sku = isset( $articolo['sku'] ) ? (string) $articolo['sku'] : '';
		$product = $sku ? wc_get_product( wc_get_product_id_by_sku( $sku ) ) : null;
		if ( ! $product || ! isset( $articolo['quantita'] ) ) {
			$non_trovati[] = $sku;
			continue;
		}
		if ( ! $product->get_manage_stock() ) {
			$product->set_manage_stock( true );
			$product->save();
		}
		wc_update_product_stock( $product, (int) $articolo['quantita'], 'set' );
		$aggiornati[] = $sku;
	}

	return array(
		'aggiornati'  => $aggiornati,
		'non_trovati' => $non_trovati,
	);
}

add_action( 'woocommerce_order_status_changed', function ( $order_id ) {
	if ( defined( 'PIEDINIFINI_MAGAZZINO_URL' ) && PIEDINIFINI_MAGAZZINO_URL ) {
		as_enqueue_async_action( 'piedinifini_magazzino_invia_ordine', array( $order_id ), 'piedinifini' );
	}
} );

add_action( 'piedinifini_magazzino_invia_ordine', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$righe = array();
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		$righe[] = array(
			'sku'      => $product ? $product->get_sku() : '',
			'quantita' => $item->get_quantity(),
		);
	}

	$risposta = wp_remote_post( PIEDINIFINI_MAGAZZINO_URL, array(
		'timeout' => 15,
		'headers' => array(
			'Content-Type'        => 'application/json',
			'X-Piedinifini-Token' => piedinifini_magazzino_token(),
		),
		'body'    => wp_json_encode( array(
			'ordine' => $order->get_order_number(),
			'stato'  => $order->get_status(),
			'data'   => $order->get_date_created()?->format( DATE_ATOM ),
			'righe'  => $righe,
		) ),
	) );

	$codice = wp_remote_retrieve_response_code( $risposta );
	if ( is_wp_error( $risposta ) || $codice < 200 || $codice >= 300 ) {
		// Un'eccezione fa segnare l'azione come fallita in Action Scheduler.
		throw new RuntimeException( 'Invio ordine al gestionale fallito: ' . ( is_wp_error( $risposta ) ? $risposta->get_error_message() : "HTTP $codice" ) );
	}
} );
