<?php
/**
 * Clients module configuration
 *
 * @package WeCoza\Clients
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

return array(
    /**
     * Module Settings
     */
    'settings' => array(
        'items_per_page' => 10,
    ),

    /**
     * Form field validation rules
     */
    'validation_rules' => array(
        'client_name' => array(
            'required' => true,
            'max_length' => 255,
        ),
        'company_registration_nr' => array(
            'required' => true,
            'max_length' => 100,
            'unique' => true,
        ),
        'contact_person_email' => array(
            'required' => true,
            'email' => true,
            'max_length' => 255,
        ),
        'contact_person_cellphone' => array(
            'required' => true,
            'max_length' => 50,
        ),
        'client_town_id' => array(
            'required' => true,
            'integer' => true,
            'min' => 1,
        ),
        'contact_person' => array(
            'required' => true,
            'max_length' => 255,
        ),
        'seta' => array(
            'required' => true,
            'max_length' => 50,
        ),
        'client_status' => array(
            'required' => true,
            'in' => array('Cold Call', 'Lead', 'Active Client', 'Lost Client'),
        ),
        'financial_year_end' => array(
            'required' => true,
            'date' => true,
        ),
        'bbbee_verification_date' => array(
            'required' => true,
            'date' => true,
        ),
        'main_client_id' => array(
            'required' => false,
            'integer' => true,
            'min' => 0,
        ),
    ),

    /**
     * SETA options (associative format)
     */
    'seta_options' => array(
        'AgriSETA' => 'AgriSETA',
        'BANKSETA' => 'BANKSETA',
        'CATHSSETA' => 'CATHSSETA',
        'CETA' => 'CETA',
        'CHIETA' => 'CHIETA',
        'ETDP SETA' => 'ETDP SETA',
        'EWSETA' => 'EWSETA',
        'FASSET' => 'FASSET',
        'FP&M SETA' => 'FP&M SETA',
        'FoodBev SETA' => 'FoodBev SETA',
        'HWSETA' => 'HWSETA',
        'INSETA' => 'INSETA',
        'LGSETA' => 'LGSETA',
        'MICT SETA' => 'MICT SETA',
        'MQA' => 'MQA',
        'PSETA' => 'PSETA',
        'SASSETA' => 'SASSETA',
        'Services SETA' => 'Services SETA',
        'TETA' => 'TETA',
        'W&RSETA' => 'W&RSETA',
        'merSETA' => 'merSETA',
    ),

    /**
     * Province options (associative format)
     */
    'province_options' => array(
        'Eastern Cape' => 'Eastern Cape',
        'Free State' => 'Free State',
        'Gauteng' => 'Gauteng',
        'KwaZulu-Natal' => 'KwaZulu-Natal',
        'Limpopo' => 'Limpopo',
        'Mpumalanga' => 'Mpumalanga',
        'Northern Cape' => 'Northern Cape',
        'North West' => 'North West',
        'Western Cape' => 'Western Cape',
    ),

    /**
     * Client status options
     */
    'client_status_options' => array(
        'Cold Call' => 'Cold Call',
        'Lead' => 'Lead',
        'Active Client' => 'Active Client',
        'Lost Client' => 'Lost Client',
    ),
);
