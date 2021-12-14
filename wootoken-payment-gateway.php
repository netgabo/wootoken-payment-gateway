<?php
/*
 * Plugin Name: 			WooToken EVM Payment Gateway
 * Version:	 				0.0.1
 * Plugin URI: 				https://solucionesenblockchain.com/wootokenevm-payment-gateway
 * Description: 			Este complemento agregará la pasarela de pago para tokens compatibles con Ethereum, Binance, Matic, Velas, Solana para realizar transacciones descentralizadas.
 * Author: 					Gabriel Estrada | Soluciones en Blockchain.
 * Author URI: 				https://solucionesenblockchain.com/
 * License URI:  			https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 		4.7.0
 * Tested up to: 			4.9.8
 *
 * Text Domain: 			wootokenevm-payment-gateway
 * Domain Path: 			/lang/
 */

if (!defined('ABSPATH')) {
	exit;
}
/**
 * Agregue un enlace al área de metainformación del complemento
 */
add_filter('plugin_row_meta', 'solblock_add_link_to_plugin_meta', 10, 4);

function solblock_add_link_to_plugin_meta($links_array, $plugin_file_name, $plugin_data, $status) {
	/**
	 * Úselo para determinar si el complemento que está funcionando actualmente es nuestro propio complemento
	 */
	if (strpos($plugin_file_name, basename(__FILE__))) {
		// Agregue el enlace correspondiente al final de la matriz
		// Si desea que se muestre en el frente, puede consultarlo array_unshift función.
		$links_array[] = '<a href="https://solucionesenblockchain.com/wootokenevm-payment-gateway">FAQ</a>';
	}
	return $links_array;
}
/**
 * Agregar configuración de nombre de complemento
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'solblock_erc20_add_settings_link');
function solblock_erc20_add_settings_link($links) {
	$settings_link = '<a href="admin.php?page=wc-settings&tab=checkout">' . __('Configuración') . '</a>';
	array_push($links, $settings_link);
	return $links;
}
/**
 * Cargar paquete de idioma i18n
 */
add_action('init', 'solblock_erc20_load_textdomain');
function solblock_erc20_load_textdomain() {
	/**
	 * El primer parámetro aquí es  __($str,'param') medio param ，Es decir, distinguir los parámetros de los diferentes dominios de paquetes de idiomas.
	 */
	load_plugin_textdomain('wootokenevm-payment-gateway', false, basename(dirname(__FILE__)) . '/lang');
}

/**
 * Agregar nuevo Gateway
 */
add_filter('woocommerce_payment_gateways', 'solblock_erc20_add_gateway_class');
function solblock_erc20_add_gateway_class($gateways) {
	$gateways[] = 'WC_solblock_Erc20_Gateway';
	return $gateways;
}
/**
 * Supervisar la solicitud de finalización de pago del complemento
 */

add_action('init', 'solblock_thankyour_request');

function solblock_thankyour_request() {
	 /**
	 * Determine si la solicitud del usuario es una ruta específica. Si la ruta se modifica aquí, debe modificarse en consecuencia payments.js Código en
	 */
	if ($_SERVER["REQUEST_URI"] == '/hook/wc_erc20') {
		$data = $_POST;
		$order_id = $data['orderid'];
		$tx = $data['tx'];
		
		if (strlen($tx) != 66 || substr($tx,0,2) != '0x'){
			return ;
		}
		/**
		 * Obtener el pedido
		 */
		$order = wc_get_order($order_id);
		/**
		 * Marcar el pago de la orden como completado
		 */
		$order->payment_complete();
		/**
		 * Agregue comentarios al pedido e indique la dirección de visualización del tx
		 * Cambiar éste parámetro (href='https://ropsten.etherscan.io/tx/) por la variable del campo 'explorador_bloques' 
		 */
		$order->add_order_note(__("Hash:", 'wootokenevm-payment-gateway') . "<br><a target='_blank' href='https://ropsten.etherscan.io/tx/" . $tx . "'>" . $tx . "</a></br>");
		/**
		 * Necesita salir, de lo contrario se mostrará el contenido de la página. Se muestra en blanco cuando sale e imprime una sección de JSON en la interfaz.
		 */
		exit();	
	}
}
/*
 * Muestra la transacción en los detalles del pedido al ser completado el Estado del pedido.
 */
add_filter( 'woocommerce_get_order_item_totals', 'account_view_order_last_order_note', 2, 4 );
function account_view_order_last_order_note( $total_rows, $order, $tax_display ){
    // Para pedidos "completados" en mi cuenta, consulte las páginas de pedidos
    if( $order->has_status('completed')  && is_wc_endpoint_url( 'view-order' ) ){

        // Obtener la última nota del pedido
        $latest_notes = wc_get_order_notes( array(
            'order_id' => $order->get_id(),
            'limit'    => 1,
            'orderby'  => 'date_created_gmt',
			'order' => 'DESC',
			'type' => 'guest', 'customer', 'limit',
        ) );

        $latest_note = current( $latest_notes );

        if ( isset( $latest_note->content ) ) {
            // Agrega una nueva fila para el seguimiento
            $total_rows['order_hash'] = array(
                'label' => __('Transacción:','woocommerce'),
                'value' => $latest_note->content
            );
        }
    }
    return $total_rows;
}
/*
 * Carga de plug-in y correspondiente class
 */
add_action('plugins_loaded', 'solblock_erc20_init_gateway_class');
function solblock_erc20_init_gateway_class() {
	/**
	 * definición class
	 */
	class WC_solblock_Erc20_Gateway extends WC_Payment_Gateway {

		/**
		 * Class constructor, more about it in Step 3
		 */
		public function __construct() {
			/**
			 * Define lo que necesitas
			 * @var string
			 */
			$this->id = 'solblock_erc20';
			/**
			 * Configuración Pago Nombre del método de pago que se muestra en la interfaz del método de pago
			 * @var [type]
			 */
			$this->method_nombre_token = __('WooToken EVM Payment ', 'wootokenevm-payment-gateway');
			/**
			 * El texto del botón que se muestra cuando el usuario realiza un pedido.
			 */
			$this->order_button_text = __('Pagar con Token' , 'wootokenevm-payment-gateway');
			/**
			 * Configuración-Pago-Introducción al método de pago que se muestra en la interfaz del método de pago
			 */
			$this->method_description = __('Si desea utilizar esta pasarela de pago, le sugerimos que lea <a href="https://solucionesenblockchain.com/wootokenevm-payment-gateway">Nuestra guía </a> before.', 'wootokenevm-payment-gateway');

			$this->supports = array(
				'products',
			); // Solo admite compra

			/**
			 * Interfaz de configuración inicial y configuración de fondo
			 */
			$this->init_settings();
			$this->init_form_fields();

			// Utilice foreach para asignar todas las configuraciones al objeto para facilitar llamadas posteriores.
			foreach ($this->settings as $setting_key => $value) {
				$this->$setting_key = $value;
			}

			/**
			 * Varios ganchos (hook)
			 */
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
			add_action('woocommerce_api_compete', array($this, 'webhook'));
			add_action('admin_notices', array($this, 'do_ssl_check'));
			add_action('woocommerce_thankyou', array($this, 'thankyou_page'));

		}

		/**
		 * Elementos de configuración de complemento
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Habilitar/Deshabilitar', 'wootokenevm-payment-gateway'),
					'label' => __('Habilitar pago con Token Personalizado', 'wootokenevm-payment-gateway'),
					'type' => 'checkbox',
					'default' => 'no',
				),
				'nombre_token' => array(
					'title' => __('Nombre del Token', 'wootokenevm-payment-gateway'),
					'type' => 'text',
					'description' => __('El nombre del token se mostrará en la página de pago', 'wootokenevm-payment-gateway'),
					'default' => 'Pago con Token Personalizado',
					'desc_tip' => true,
				),
				'description' => array(
					'title' => __('Descripción', 'wootokenevm-payment-gateway'),
					'type' => 'textarea',
					'description' => __('La descripción se mostrará en la página de pago', 'wootokenevm-payment-gateway'),
					'default' => __('Asegúrese de que ya ha instalado Metamask y habilítelo.', 'wootokenevm-payment-gateway'),
				),
				'icon' => array(
					'title' => __('Dirección URL del Icono personalizado de tu token', 'wootokenevm-payment-gateway'),
					'type' => 'text',
					'default' => 'https://gateway.pinata.cloud/ipfs/QmTNUSnDf6LFbVhUcMJgFKw4N33MVYncaVUP7grDLSxWs7',
					'description' => __('Altura de la imagen: 100px', 'wootokenevm-payment-gateway'),
				),
				'target_address' => array(
					'title' => __('Dirección de billetera', 'wootokenevm-payment-gateway'),
					'type' => 'text',
					'description' => __('El token se transferirá a esta billetera', 'wootokenevm-payment-gateway'),
				),
				'explorardor_bloques' => array(
					'title' => __('URL del explorador de bloques', 'wootokenevm-payment-gateway'),
					'type' => 'text',
					'description' => __('Coloque el explorardo de bloques de la blockchain Ej.: https://ropsten.etherscan.io', 'wootokenevm-payment-gateway'),
				),
				'abi_array' => array(
					'title' => __('Contrato ABI', 'wootokenevm-payment-gateway'),
					'type' => 'textarea',
					'description' => __('Puede obtener el Contrato ABI desde su explorador de contrator ejemplo: Etherscan.io', 'wootokenevm-payment-gateway'),
				),
				'contract_address' => array(
					'title' => __('Dirección del contrato', 'wootokenevm-payment-gateway'),
					'type' => 'text',
				),
				'gas_notice' => array(
					'title' => __('Aviso de gas', 'wootokenevm-payment-gateway'),
					'type' => 'textarea',
					'default' => __('Establezca un precio de GAS más alto para acelerar su transacción.', 'wootokenevm-payment-gateway'),
					'description' => __('Dígale a su Cliente que fijó un alto precio del GAS para acelerar la transacción.', 'wootokenevm-payment-gateway'),
				),
			);
			$this->form_fields += array(

				'ad1' => array(
					'title' => 'Plugin completamente gratuito',
					'type' => 'title',
					'description' => 'Existe gracias a los aprotes de los programadores la código y los donativos que puedas hacer. <br> Si desdeas ayudar con un pequeño donativo sería de mucha ayuda para continuar desarrollando el proyecto.</br><br>Si eres desarrollador podrías colaborar añadiendo código que ayude a mejorar la experiencia de uso del mismo.</br><br>También puedes enviar tus propios tokens :)</br><br>BITCOIN: 1AaSuCNtxuBnab5aCP217acMC1eSMmAvgM</br><br>ETH: 0x97103971cF63fBC527B4Eb3FC2ffEBDb832cFe62</br><br>Binance BNB/BSC: 0x3c745E8A305548f5E974251a8086AA0B4E88A320</br>  ',
				),
				'ad2' => array(
					'title' => 'Dirección de contacto',
					'type' => 'title',
					'description' => 'mi correo electrónico <a href="mailto:netgabo@gmail.com">netgabo@gmail.com</a> Contáctame',
				),
				
			);
		}
		/**
		 * Cargue el pago en la recepción JavaScript
		 */
		public function payment_scripts() {
			wp_enqueue_script('solblock_web3', plugins_url('assets/web3.min.js', __FILE__), array('jquery'), 1.1, true);
			wp_register_script('solblock_payments', plugins_url('assets/payments.js', __FILE__), array('jquery', 'solblock_web3'));
			wp_enqueue_script('solblock_payments');
		}

		/**
		 * No realice la validación del formulario porque no hay ningún formulario establecido en la página de pago.
		 */
		public function validate_fields() {
			return true;
		}

		/**
		 * El siguiente paso en la página de pago del usuario
		 */
		public function process_payment($order_id) {
			global $woocommerce;
			$order = wc_get_order($order_id);
			/**
			 * Marca el pedido como impago.
			 */
			$order->add_order_note(__('Ordern creada, esperando comprobación de bloques', 'wootokenevm-payment-gateway'));
			/**
			 * Establezca el estado del pedido unpaid, puede usar need_payments para monitorearlo más tarde
			 */
			$order->update_status('unpaid', __('Espere el pago...', 'wootokenevm-payment-gateway'));
			/**
			 * disminuir stock
			 */
			$order->reduce_order_stock();
			/**
			 * Carro de compras vacío
			 */
			WC()->cart->empty_cart();
			/**
			 * El pago es exitoso, ingrese a la página de agradecimiento
			 */
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order),
			);
		}
		/**
		 * Compruebe si se utiliza SSL para garantizar la seguridad.
		 */
		public function do_ssl_check() {
			if ($this->enabled == "yes") {
				if (get_option('woocommerce_force_ssl_checkout') == "no") {
					echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong>WooToken ERC20: SSL no está habilitado y WooCommerce no está forzando el certificado SSL en su página de pago. Asegúrese de tener un certificado SSL válido y de que está <a href=\"%s\">obligando a proteger las páginas de pago.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
				}
			}
		}
		/**
		 * Gracias. Configuración de la página
		 * Necesito recordar a los usuarios que paguen.
		 */
		public function thankyou_page($order_id) {
			/**
			 * Si no se pasa order_id, regresará.
			 */
			if (!$order_id) {
				return;
			}
			
			$order = wc_get_order($order_id);
			/**
			 * Supervisar si el pedido debe pagarse
			 */
			if ($order->needs_payment()) {
				/**
				 * Si se requiere el pago, se emite la información del pedido.
				 */
				echo '<script>var order_id = ' . $order_id . ';var contract_address = "' . (string) $this->contract_address . '";var abiArray = ' . $this->abi_array . '; var target_address = "' . $this->target_address . '"; </script>';
				echo __('<h2 class="h2thanks">Utilice Metamask y Pague este pedido</h2>', 'wootokenevm-payment-gateway');
				echo __('<br>Haga clic en el botón de abajo, pagar este pedido.</br><br><div style= "color: #ff2e00;"><b>Asegúsere de estar en la blockchain correcta: ' . $this->explorardor_bloques. '</b></br></div>', 'wootokenevm-payment-gateway');
				echo '<br><span style="margin:5px 0px;">' . $this->gas_notice . "</span></br>";
				echo '<div><button style="width: 100%; background-color: #ffa800; border-color: #eeeeee; color: #ffffff; border-radius: 10px;
				margin-top: 15px;" onclick="requestPayment(' . (string) $order->get_total() . ')">' . __('Abrir Metamask', 'wootokenevm-payment-gateway') . '</button></div>';

			} else {
				$block_explorer_link = 'explorardor_bloques';
				$direccion_wallet = '$this->contract_address';
				echo "<style> .mens {
					position: relative;
					padding: 20px 20px 32px 20px;
					background: #f8f8f8;
					text-align-last: center;
					font-size: larger;
					}
					.mens:after {
					background: linear-gradient(-45deg, #ffffff 16px, transparent 0), linear-gradient(45deg, #ffffff 16px, transparent 0);
					background-position: left-bottom;
					background-repeat: repeat-x;
					background-size: 32px 32px;
					content: ' ';
					display: block;
					position: absolute;
					bottom: 0px;
					left: 0px;
					width: 100%;
					height: 32px;
					}</style>";	
				/**
				 * No es necesario pagar; significa que la transferencia fue realizada.
				 */
				$notes = wc_get_order_notes( array('order_id' => $order_id, 'type' => 'guest', 'customer', 'limit'    => 1,
				'orderby'  => 'date_created_gmt'// use 'internal' para admin y notas del sistema, dejar vacío para mostrar todo.
				) );	
				echo "<div class='mens'>Usted acaba de realiar un depósito a la siguiente dirección:<br> <a target='_blank' href='". $this->explorardor_bloques ."/address/$this->target_address?a=$this->contract_address#tokentxns'>$this->target_address</a></br> En breve su pedido será verificado y procesado, Gracias</div>";
				if ( $notes ) {
					foreach( $notes as $key => $note ) {
						echo '<div class="mens">' .$note->content. '</div>';
					}
				}
			exit();
			}
		}
	}
}

