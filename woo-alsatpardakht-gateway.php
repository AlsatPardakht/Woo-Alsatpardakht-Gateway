<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


function Load_AlsatPardakht_Gateway() {

    if ( ! function_exists( 'Woocommerce_Add_AlsatPardakht_Gateway' ) && class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'WC_ALSATPARDAKHT' ) ) {


        add_filter( 'woocommerce_payment_gateways', 'Woocommerce_Add_AlsatPardakht_Gateway' );

        function Woocommerce_Add_AlsatPardakht_Gateway( $methods ) {
            $methods[] = 'WC_ALSATPARDAKHT';

            return $methods;
        }

        add_filter( 'woocommerce_currencies', 'add_IR_currency' );

        function add_IR_currency( $currencies ) {
            $currencies['IRR']  = __( 'ریال', 'woocommerce' );
            $currencies['IRT']  = __( 'تومان', 'woocommerce' );
            $currencies['IRHR'] = __( 'هزار ریال', 'woocommerce' );
            $currencies['IRHT'] = __( 'هزار تومان', 'woocommerce' );

            return $currencies;
        }

        add_filter( 'woocommerce_currency_symbol', 'add_IR_currency_symbol', 10, 2 );

        function add_IR_currency_symbol( $currency_symbol, $currency ) {
            switch ( $currency ) {
                case 'IRR':
                    $currency_symbol = 'ریال';
                    break;
                case 'IRT':
                    $currency_symbol = 'تومان';
                    break;
                case 'IRHR':
                    $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT':
                    $currency_symbol = 'هزار تومان';
                    break;
            }

            return $currency_symbol;
        }

        class WC_ALSATPARDAKHT extends WC_Payment_Gateway {


            private $merchantCode;
            private $failedMassage;
            private $successMassage;
            private $is_vaset_ipg;

            public function __construct() {

                $this->id                 = 'WC_ALSATPARDAKHT';
                $this->method_title       = __( 'پرداخت امن آلسات پرداخت', 'woocommerce' );
                $this->method_description = __( 'تنظیمات درگاه پرداخت آلسات پرداخت برای افزونه فروشگاه ساز ووکامرس',
                    'woocommerce' );
                $this->icon               = apply_filters( 'WC_ALSATPARDAKHT_logo',
                    WP_PLUGIN_URL . '/' . plugin_basename( __DIR__ ) . '/assets/images/logo.png' );
                $this->has_fields         = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title       = sanitize_text_field( $this->settings['title'] );
                $this->description = sanitize_text_field( $this->settings['description'] );

                $this->merchantCode = sanitize_text_field( $this->settings['merchantcode'] );
                $this->is_vaset_ipg = sanitize_text_field( $this->settings['is_vaset_ipg'] );

                $this->successMassage = esc_html( $this->settings['success_massage'] );
                $this->failedMassage  = esc_html( $this->settings['failed_massage'] );

                if ( version_compare( $this->get_woo_version_number(), '2.0.0', '>=' ) ) {
                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
                        array( $this, 'process_admin_options' ) );
                } else {
                    add_action( 'woocommerce_update_options_payment_gateways',
                        array( $this, 'process_admin_options' ) );
                }

                add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'Send_to_AlsatPardakht_Gateway' ) );
                add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ),
                    array( $this, 'Return_from_AlsatPardakht_Gateway' ) );
            }

            public function init_form_fields() {
                $this->form_fields = apply_filters( 'WC_ALSATPARDAKHT_Config', array(
                        'base_config'     => array(
                            'title'       => __( 'تنظیمات پایه ای', 'woocommerce' ),
                            'type'        => 'title',
                            'description' => '',
                        ),
                        'enabled'         => array(
                            'title'       => __( 'فعالسازی/غیرفعالسازی', 'woocommerce' ),
                            'type'        => 'checkbox',
                            'label'       => __( 'فعالسازی درگاه آلسات پرداخت', 'woocommerce' ),
                            'description' => __( 'برای فعالسازی درگاه پرداخت آلسات پرداخت باید چک باکس را تیک بزنید',
                                'woocommerce' ),
                            'default'     => 'yes',
                            'desc_tip'    => true,
                        ),
                        'is_vaset_ipg'    => array(
                            'title'       => __( 'درگاه مستقیم/درگاه واسط', 'woocommerce' ),
                            'type'        => 'checkbox',
                            'label'       => __( 'فعال سازی درگاه واسط آلسات پرداخت', 'woocommerce' ),
                            'description' => __( 'در صورتی که درگاه پرداخت شما درگاه مستقیم میباشد نیازی به فعال سازی این قسمت نمیباشد.',
                                'woocommerce' ),
                            'default'     => 'no',
                            'desc_tip'    => true,
                        ),
                        'title'           => array(
                            'title'       => __( 'عنوان درگاه', 'woocommerce' ),
                            'type'        => 'text',
                            'description' => __( 'عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce' ),
                            'default'     => __( 'پرداخت امن آلسات پرداخت', 'woocommerce' ),
                            'desc_tip'    => true,
                        ),
                        'description'     => array(
                            'title'       => __( 'توضیحات درگاه', 'woocommerce' ),
                            'type'        => 'text',
                            'desc_tip'    => true,
                            'description' => __( 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد',
                                'woocommerce' ),
                            'default'     => __( 'پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه آلسات پرداخت',
                                'woocommerce' )
                        ),
                        'account_config'  => array(
                            'title'       => __( 'تنظیمات حساب آلسات پرداخت', 'woocommerce' ),
                            'type'        => 'title',
                            'description' => '',
                        ),
                        'merchantcode'    => array(
                            'title'       => __( 'کد API', 'woocommerce' ),
                            'type'        => 'text',
                            'description' => __( 'کد API درگاه آلسات پرداخت', 'woocommerce' ),
                            'default'     => '',
                            'desc_tip'    => true
                        ),
                        'payment_config'  => array(
                            'title'       => __( 'تنظیمات عملیات پرداخت', 'woocommerce' ),
                            'type'        => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title'       => __( 'پیام پرداخت موفق', 'woocommerce' ),
                            'type'        => 'textarea',
                            'description' => __( 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) آلسات پرداخت استفاده نمایید .',
                                'woocommerce' ),
                            'default'     => __( 'با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce' ),
                        ),
                        'failed_massage'  => array(
                            'title'       => __( 'پیام پرداخت ناموفق', 'woocommerce' ),
                            'type'        => 'textarea',
                            'description' => __( 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت آلسات پرداخت ارسال میگردد .',
                                'woocommerce' ),
                            'default'     => __( 'پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .',
                                'woocommerce' ),
                        ),
                    )
                );
            }

            public function process_payment( $order_id ): array {
                $order = new WC_Order( $order_id );

                return array(
                    'result'   => 'success',
                    'redirect' => $order->get_checkout_payment_url( true )
                );
            }

            /**
             * @param $action  (PaymentRequest, )
             * @param  array  $params  string
             *
             * @return mixed
             */
            public function SendRequestToAlsatPardakht( $action, array $params ) {
                try {
                    $args   = array(
                        'body'        => $params,
                        'timeout'     => '30',
                        'redirection' => '5',
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'headers'     => array(),
                        'cookies'     => array(),
                    );
                    $result = wp_safe_remote_post( $action, $args );

                    if ( ! isset( $result->errors ) ) {
                        if ( isset( $result['body'] ) && $result['body'] ) {
                            $result = json_decode( $result['body'] );
                        } else {
                            $result = json_decode( '[]' );
                        }

                    }

                    return $result;
                } catch ( Exception $ex ) {
                    return false;
                }
            }

            public function Send_to_AlsatPardakht_Gateway( $order_id ) {


                global $woocommerce;
                $woocommerce->session->order_id_AlsatPardakht = $order_id;
                $order                                        = new WC_Order( $order_id );
                $currency                                     = $order->get_currency();
                $currency                                     = apply_filters( 'WC_ALSATPARDAKHT_Currency', $currency,
                    $order_id );
                $Fault = '';
                $form = '<form action="" method="POST" class="AlsatPardakht-checkout-form" id="AlsatPardakht-checkout-form" style="direction:ltr;">
						<input type="submit" name="AlsatPardakht_submit" class="button alt" id="AlsatPardakht-payment-button" value="' . __( 'پرداخت',
                        'woocommerce' ) . '"/>
						<a class="button cancel" href="' . wc_get_checkout_url() . '">' . __( 'بازگشت',
                        'woocommerce' ) . '</a>
					 </form><br/>';
                $form = apply_filters( 'WC_ALSATPARDAKHT_Form', $form, $order_id, $woocommerce );

                do_action( 'WC_ALSATPARDAKHT_Gateway_Before_Form', $order_id, $woocommerce );
                echo esc_html( $form );
                do_action( 'WC_ALSATPARDAKHT_Gateway_After_Form', $order_id, $woocommerce );


                $Amount = $this->getAmount( $order, $currency );

                $CallbackUrl = add_query_arg( 'wc_order', $order_id, WC()->api_request_url( 'WC_ALSATPARDAKHT' ) );

                if ( $this->is_vaset_ipg === 'yes' ) {
                    $Tashim[] = [];
                    $data     = array(
                        'Amount'              => (int) $Amount,
                        'ApiKey'              => $this->merchantCode,
                        'Tashim'              => json_encode( $Tashim ),
                        'RedirectAddressPage' => $CallbackUrl,
                    );

                    $result = $this->SendRequestToAlsatPardakht( 'https://www.alsatpardakht.com/IPGAPI/Api22/send.php',
                        $data );
                } else {
                    $data   = array(
                        'Api'             => $this->merchantCode,
                        'Amount'          => (int) $Amount,
                        'InvoiceNumber'   => (int) $order->get_order_number(),
                        'RedirectAddress' => $CallbackUrl,
                    );
                    $result = $this->SendRequestToAlsatPardakht( 'https://www.alsatpardakht.com/API_V1/sign.php',
                        $data );
                }
                if ( ! $result || isset( $result->errors ) ) {
                    if ( is_array( $result->errors[ $result->get_error_code() ] ) ) {
                        foreach ( $result->errors[ $result->get_error_code() ] as $error ) {
                            echo esc_html( $error ) . "<br>";
                        }
                    } else {
                        echo esc_html( $result->errors[ $result->get_error_code() ] );
                    }
                } elseif ( isset( $result->IsSuccess ) && isset( $result->Token ) && $result->IsSuccess === 1 && $result->Token ) {
                    if ( $this->is_vaset_ipg === 'yes' ) {
                        wp_redirect( sprintf( 'https://www.alsatpardakht.com/API_V1/Go.php?Token=' . $result->Token,
                            $result->Token ) );
                    } else {
                        wp_redirect( sprintf( 'https://www.alsatpardakht.com/IPGAPI/Api2/Go.php?Token=' . $result->Token,
                            $result->Token ) );
                    }
                    exit;
                } else {
                    $Message = 'خطای اتصال به وب سرویس';
                }

                if ( ! empty( $Message ) ) {

                    $Note = sprintf( __( 'خطا در هنگام ارسال به بانک : %s', 'woocommerce' ), $Message );
                    $Note = apply_filters( 'WC_ALSATPARDAKHT_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault );
                    $order->add_order_note( $Note );


                    $Notice = sprintf( __( 'در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce' ),
                        $Message );
                    $Notice = apply_filters( 'WC_ALSATPARDAKHT_Send_to_Gateway_Failed_Notice', $Notice, $order_id,
                        $Fault );
                    if ( $Notice ) {
                        wc_add_notice( $Notice, 'error' );
                    }

                    do_action( 'WC_ALSATPARDAKHT_Send_to_Gateway_Failed', $order_id, $Fault );
                }
            }

            private function getAmount( $order, $currency ) {
                $Amount = (int) $order->get_total();

                $Amount             = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency',
                    $Amount, $currency );
                $strToLowerCurrency = strtolower( $currency );
                if (
                    ( $strToLowerCurrency === strtolower( 'IRT' ) ) ||
                    ( $strToLowerCurrency === strtolower( 'TOMAN' ) ) ||
                    $strToLowerCurrency === strtolower( 'Iran TOMAN' ) ||
                    $strToLowerCurrency === strtolower( 'Iranian TOMAN' ) ||
                    $strToLowerCurrency === strtolower( 'Iran-TOMAN' ) ||
                    $strToLowerCurrency === strtolower( 'Iranian-TOMAN' ) ||
                    $strToLowerCurrency === strtolower( 'Iran_TOMAN' ) ||
                    $strToLowerCurrency === strtolower( 'Iranian_TOMAN' ) ||
                    $strToLowerCurrency === strtolower( 'تومان' ) ||
                    $strToLowerCurrency === strtolower( 'تومان ایران' )
                ) {
                    $Amount *= 10;
                } elseif ( $strToLowerCurrency === strtolower( 'IRHT' ) ) {
                    $Amount *= 10000;
                } elseif ( $strToLowerCurrency === strtolower( 'IRHR' ) ) {
                    $Amount *= 1000;
                }

                return $Amount;
            }

            public function Return_from_AlsatPardakht_Gateway() {

                $Transaction_ID = 0;
                global $woocommerce;

                if ( isset( $_GET['wc_order'] ) ) {
                    $order_id = wc_sanitize_order_id( $_GET['wc_order'] );
                } else {
                    $order_id = $woocommerce->session->order_id_AlsatPardakht;
                    unset( $woocommerce->session->order_id_AlsatPardakht );
                }
                try {
                    $order = new WC_Order( $order_id );
                } catch ( Exception $e ) {
                    $order = null;
                }

                if ( $order ) {

                    $currency = $order->get_currency();
                    $currency = apply_filters( 'WC_ALSATPARDAKHT_Currency', $currency, $order_id );

                    if ( $order->get_status() !== 'completed' ) {

                        if ( isset( $_GET['tref'] ) && isset( $_GET['iN'] ) && $_GET['iD'] ) {

                            $tref = sanitize_text_field( $_GET['tref'] );
                            $iN   = wc_sanitize_order_id( $_GET['iN'] );
                            $iD   = sanitize_text_field( $_GET['iD'] );

                            $Amount = $this->getAmount( $order, $currency );


                            $data = [
                                "Api"  => $this->merchantCode,
                                "tref" => $tref,
                                "iN"   => $iN,
                                "iD"   => $iD
                            ];

                            if ( $this->is_vaset_ipg === 'yes' ) {
                                $result = $this->SendRequestToAlsatPardakht( "https://www.alsatpardakht.com/IPGAPI/Api22/VerifyTransaction.php",
                                    $data );
                            } else {
                                $result = $this->SendRequestToAlsatPardakht( "https://www.alsatpardakht.com/API_V1/callback.php",
                                    $data );
                            }
                            if ( isset( $result->VERIFY->IsSuccess ) && isset( $result->PSP ) && $result->PSP->IsSuccess === true ) {

                                if ( $result->PSP->Amount === $Amount ) {
                                    $Status         = 'completed';
                                    $Transaction_ID = $result->PSP->TransactionReferenceID;
                                    $Fault          = '';
                                    $Message        = '';
                                } else {
                                    $Status  = 'failed';
                                    $Fault   = 0;
                                    $Message = 'تراکنش ناموفق بود';
                                }
                            } else {
                                $Status  = 'failed';
                                $Fault   = 0;
                                $Message = 'تراکنش ناموفق بود';
                            }
                        } else {

                            $Status  = 'failed';
                            $Fault   = '';
                            $Message = 'تراکنش انجام نشد .';
                        }
                        if ( $Status === 'completed' && isset( $Transaction_ID ) && $Transaction_ID !== 0 ) {
                            update_post_meta( $order_id, '_transaction_id', $Transaction_ID );


                            $order->payment_complete( $Transaction_ID );
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf( __( 'پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce' ),
                                $Transaction_ID );
                            $Note = apply_filters( 'WC_ALSATPARDAKHT_Return_from_Gateway_Success_Note', $Note,
                                $order_id, $Transaction_ID );
                            if ( $Note ) {
                                $order->add_order_note( $Note, 1 );
                            }


                            $Notice = wpautop( wptexturize( $this->successMassage ) );

                            $Notice = str_replace( '{transaction_id}', $Transaction_ID, $Notice );

                            $Notice = apply_filters( 'WC_ALSATPARDAKHT_Return_from_Gateway_Success_Notice', $Notice,
                                $order_id, $Transaction_ID );
                            if ( $Notice ) {
                                wc_add_notice( $Notice );
                            }

                            do_action( 'WC_ALSATPARDAKHT_Return_from_Gateway_Success', $order_id, $Transaction_ID );

                            wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
                            exit;
                        }

                        if ( ( isset( $Transaction_ID ) && ( $Transaction_ID != 0 ) ) ) {
                            $tr_id = ( '<br/>توکن : ' . $Transaction_ID );
                        } else {
                            $tr_id = '';
                        }

                        $Note = sprintf( __( 'خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce' ), $Message, $tr_id );

                        $Note = apply_filters( 'WC_ALSATPARDAKHT_Return_from_Gateway_Failed_Note', $Note, $order_id,
                            $Transaction_ID, $Fault );
                        if ( $Note ) {
                            $order->add_order_note( $Note, 1 );
                        }

                        $Notice = wpautop( wptexturize( $this->failedMassage ) );

                        $Notice = str_replace( array( '{transaction_id}', '{fault}' ),
                            array( $Transaction_ID, $Message ), $Notice );

                        $Notice = apply_filters( 'WC_ALSATPARDAKHT_Return_from_Gateway_Failed_Notice', $Notice,
                            $order_id, $Transaction_ID, $Fault );

                        if ( $Notice ) {
                            wc_add_notice( $Notice, 'error' );
                        }

                        do_action( 'WC_ALSATPARDAKHT_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault );
                        wp_redirect( wc_get_checkout_url() );
                        exit;

                    }

                    $Transaction_ID = get_post_meta( $order_id, '_transaction_id', true );

                    $Notice = wpautop( wptexturize( $this->successMassage ) );

                    $Notice = str_replace( '{transaction_id}', $Transaction_ID, $Notice );

                    $Notice = apply_filters( 'WC_ALSATPARDAKHT_Return_from_Gateway_ReSuccess_Notice', $Notice,
                        $order_id, $Transaction_ID );
                    if ( $Notice ) {
                        wc_add_notice( $Notice );
                    }

                    do_action( 'WC_ALSATPARDAKHT_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID );

                    wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
                    exit;
                }

                $Fault  = __( 'شماره سفارش وجود ندارد .', 'woocommerce' );
                $Notice = wpautop( wptexturize( $this->failedMassage ) );
                $Notice = str_replace( '{fault}', $Fault, $Notice );
                $Notice = apply_filters( 'WC_ALSATPARDAKHT_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id,
                    $Fault );
                if ( $Notice ) {
                    wc_add_notice( $Notice, 'error' );
                }

                do_action( 'WC_ALSATPARDAKHT_Return_from_Gateway_No_Order_ID', $order_id, '0', $Fault );

                wp_redirect( wc_get_checkout_url() );
                exit;
            }

            function get_woo_version_number() {
                // If get_plugins() isn't available, require it
                if ( ! function_exists( 'get_plugins' ) )
                    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

                // Create the plugins folder and file variables
                $plugin_folder = get_plugins( '/' . 'woocommerce' );
                $plugin_file = 'woocommerce.php';

                // If the plugin version number is set, return it
                return $plugin_folder[ $plugin_file ]['Version'] ?: null ;
            }

        }

    }

}

add_action( 'plugins_loaded', 'Load_AlsatPardakht_Gateway', 0 );
