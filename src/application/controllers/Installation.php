<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2018, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.1.0
 * ---------------------------------------------------------------------------- */

/**
 * Installation Controller
 *
 * This controller will handle the installation procedure of Easy!Appointments
 *
 * @package Controllers
 */
class Installation extends CI_Controller {
    /**
     * Class Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('installation');
        $this->load->library('session');

        // Set user's selected language.
        if ($this->session->userdata('language'))
        {
            $this->config->set_item('language', $this->session->userdata('language'));
            $this->lang->load('translations', $this->session->userdata('language'));
        }
        else
        {
            $this->lang->load('translations', $this->config->item('language')); // default
        }
    }

    /**
     * Display the installation page.
     */
    public function index()
    {
        if (is_ea_installed())
        {
            redirect('appointments/index');
            return;
        }

        $this->load->view('general/installation', [
            'base_url' => $this->config->item('base_url')
        ]);
    }

    /**
     * [AJAX] Installs Easy!Appointments on the server.
     *
     * Required POST Parameters
     *
     * - array $_POST['admin'] Contains the initial admin user data. The App needs at least one admin user to work.
     * - array $_POST['company'] Contains the basic company data.
     */
    public function ajax_install()
    {
        try
        {
            if (is_ea_installed())
            {
                return;
            }

            // Create E!A database structure.
            $file_contents = file_get_contents(dirname(BASEPATH) . '/assets/sql/structure.sql');
            $sql_queries = explode(';', $file_contents);
            array_pop($sql_queries);
            foreach ($sql_queries as $query)
            {
                $this->db->query($query);
            }

            // Insert default E!A entries into the database.
            $file_contents = file_get_contents(dirname(BASEPATH) . '/assets/sql/data.sql');
            $sql_queries = explode(';', $file_contents);
            array_pop($sql_queries);
            foreach ($sql_queries as $query)
            {
                $this->db->query($query);
            }

	    // {"admin": {"first_name": "John",					* optional
	    //            "last_name": "Doe",					* required
	    //            "email": "admin.email@example.com",			* required
	    //            "phone_number": "555-0199",				* required
	    //            "username": "admin_username",				* required
	    //            "password": "admin_password"},			* required
	    //  "company": {"company_name": "company name",			* required
	    //              "company_email": "company.email@example.com",	* required
	    //              "company_link": "https://company.link/"},		* required
	    //  "load_sample_data": false}					* optional
	    $input = json_decode(file_get_contents('php://input'));

            // Insert admin
            $this->load->model('admins_model');
	    $admin = Array();
	    $admin['settings']['username'] = $input->admin->username;
	    $admin['settings']['password'] = $input->admin->password;
	    $admin['first_name'] = $input->admin->first_name;
	    $admin['last_name'] = $input->admin->last_name;
	    $admin['phone_number'] = $input->admin->phone_number;
	    $admin['email'] = $input->admin->email;
	    /* FIXME use codeignitor magic
            $admin = $this->input->post('admin');
            $admin['settings']['username'] = $admin['username'];
            $admin['settings']['password'] = $admin['password'];
            $admin['settings']['calendar_view'] = CALENDAR_VIEW_DEFAULT;
            unset($admin['username'], $admin['password']);
	    */
            $admin['id'] = $this->admins_model->add($admin);

            $this->load->library('session');
            $this->session->set_userdata('user_id', $admin['id']);
            $this->session->set_userdata('user_email', $admin['email']);
            $this->session->set_userdata('role_slug', DB_SLUG_ADMIN);
            $this->session->set_userdata('username', $admin['settings']['username']);

            // Save company settings
            $this->load->model('settings_model');
            $this->settings_model->set_setting('company_name', $input->company->company_name);
            $this->settings_model->set_setting('company_email', $input->company->company_email);
            $this->settings_model->set_setting('company_link', $input->company->company_link);
	    /* FIXME use codeignitor magic
            $company = $this->input->post('company');
            $this->settings_model->set_setting('company_name', $company['company_name']);
            $this->settings_model->set_setting('company_email', $company['company_email']);
            $this->settings_model->set_setting('company_link', $company['company_link']);
	    */

            // Create sample records.
            $this->load->model('services_model');
            $this->load->model('providers_model');

            if(isset($input->load_sample_data) && $input->load_sample_data){
	            $sample_service = get_sample_service();
		    $sample_service['id'] = $this->services_model->add($sample_service);
		    $sample_provider = get_sample_provider();
		    $sample_provider['services'][] = $sample_service['id'];
		    $this->providers_model->add($sample_provider);
	    }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(AJAX_SUCCESS));

        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }
}
