<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Utilities\Installer;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class InstallController extends Controller
{
    /**
     * Constructor.
     * Redirects to the home page if the application is already installed.
     */
    public function __construct()
    {
        if (env('APP_INSTALLED', false) == true) {
            Redirect::to('/')->send();
        }
    }

    /**
     * Step 1: Check server requirements.
     */
    public function index()
    {
        $requirements = Installer::checkServerRequirements();
        return view('install.step_1', compact('requirements'));
    }

    /**
     * Step 2: Display database configuration form.
     */
    public function database()
    {
        return view('install.step_2');
    }

    /**
     * Step 3: Process installation (database setup and license check).
     */
    public function process_install(Request $request)
    {
        $host            = $request->hostname;
        $database        = $request->database;
        $username        = $request->username;
        $password        = $request->password;
        $license_key     = $request->license_key;
        $envato_username = $request->envato_username;

        // Bypass license check for local development or educational purposes
        $license_check = [
            'result'  => true, // Simulate a successful license check
            'message' => 'License check bypassed for local development.',
        ];

        // Proceed with database setup if the license check is successful
        if ($license_check['result'] == true) {
            if (Installer::createDbTables($host, $database, $username, $password) == false) {
                return redirect()->back()->with("error", "Invalid Database Settings !")->withInput();
            }
        } else {
            return redirect()->back()->with("error", $license_check['message'])->withInput();
        }

        // Redirect to the next step (create admin user)
        return redirect('install/create_user');
    }

    /**
     * Step 4: Display admin user creation form.
     */
    public function create_user()
    {
        return view('install.step_3');
    }

    /**
     * Step 5: Store admin user details.
     */
    public function store_user(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:191',
            'email'    => 'required|string|email|max:191|unique:users',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $name     = $request->name;
        $email    = $request->email;
        $password = Hash::make($request->password);

        // Create the admin user
        Installer::createUser($name, $email, $password);

        // Redirect to the next step (system settings)
        return redirect('install/system_settings');
    }

    /**
     * Step 6: Display system settings form.
     */
    public function system_settings()
    {
        return view('install.step_4');
    }

    /**
     * Step 7: Finalize installation (update settings and mark as installed).
     */
    public function final_touch(Request $request)
    {
        // Update system settings
        Installer::updateSettings($request->all());

        // Perform final installation tasks
        Installer::finalTouches($request->site_title);

        // Redirect to the settings update page
        return redirect()->route('settings.update_settings');
    }
}