<div class="page page-center">
    <div class="row container container-xl py-8 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
                <span class="avatar avatar-lg me-2" style="background-image: url(/assets/common/img/vaded-app-logo.png)"></span>
                <h1 class="mb-0">Vaded Hosting</h1>
            </div>
            <div>
                <div class="d-flex d-block">

                    <div class="dropdown me-2">
                        <button class="nav-link px-0 me-2 d-flex align-items-center" id="languageSwitcher" data-bs-toggle="dropdown" aria-expanded="false" data-bs-placement="bottom">
                            <span class="flag flag-xxs flag-country-us me-1"></span>
                            English
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageSwitcher" style="">
                            <li>
                                <a class="dropdown-item  active  text-decoration-none text-body" wire:navigate="" href="#">
                            <span class="ms-2 d-flex align-items-center ">
                                <span class="flag flag-xxs flag-country-us me-1"></span>

                                English (EN)
                            </span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <a wire:navigate="" href="?theme=dark" class="nav-link px-0 hide-theme-dark me-2" data-bs-toggle="tooltip" data-bs-placement="bottom" aria-label="Enable dark mode" data-bs-original-title="Enable dark mode">
                        <i class="ti ti-moon icon"></i>
                    </a>
                    <a wire:navigate="" href="?theme=light" class="nav-link px-0 hide-theme-light me-2" data-bs-toggle="tooltip" data-bs-placement="bottom" aria-label="Enable light mode" data-bs-original-title="Enable light mode">
                        <i class="ti ti-sun icon"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-4 d-none d-lg-block">
            <div class="card">
                <div class="card-body">
                    <ul class="steps steps-vertical">
                        <li class="step-item @if($step == 'requirements') active @endif">
                            <div class="h4 m-0">Server Requirements</div>
                            <div class="text-secondary">
                                Ensure that your server meets the requirements for running the application. This includes PHP version, extensions, and other necessary configurations.
                            </div>
                        </li>
                        <li class="step-item @if($step == 'eula') active @endif">
                            <div class="h4 m-0">End User License Agreement</div>
                            <div class="text-secondary">
                                Please read and accept the End User License Agreement to proceed with the installation.
                            </div>
                        </li>
                        <li class="step-item @if($step == 'activation') active @endif">
                            <div class="h4 m-0">License Activation</div>
                            <div class="text-secondary">
                                Enter the license key supplied by your application administrator to activate the application.
                            </div>
                        </li>
                        <li class="step-item @if($step == 'database') active @endif">
                            <div class="h4 m-0">Database Setup</div>
                            <div class="text-secondary">
                                Connect to your database by providing the database host, name, username, and password.
                            </div>
                        </li>
                        <li class="step-item @if($step == 'setup') active @endif">
                            <div class="h4 m-0">Configure Application</div>
                            <div class="text-secondary">
                                Configure your application settings, such as the application name, URL, and admin credentials.
                            </div>
                        </li>
                        <li class="step-item @if($step == 'create_user') active @endif">
                            <div class="h4 m-0">Create Administrator Account</div>
                            <div class="text-secondary">
                                Create an administrator account to manage your application.
                            </div>
                        </li>
                        <li class="step-item @if($step == 'complete') active @endif">
                            <div class="h4 m-0">Finalized</div>
                            <div class="text-secondary">
                                Your application is ready to use! You can now log in with the administrator account you created.
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        @if($step == 'requirements')
        <div class="col-12 col-lg-8">
            <div class="card card-md">
                <div class="card-body py-4 p-sm-5">
                    <div class="text-center">
                        <h1>Vaded Hosting Application Installer</h1>
                        <p class="text-secondary">
                            This installer will help you set up your Vaded Hosting application. Please follow the steps carefully to ensure a successful installation.
                        </p>
                    </div>
                </div>
                <div class="hr-text hr-text-center hr-text-spaceless">Server Requirements</div>
                <div class="card-body">
                    @if(!$areRequirementsMet)
                        <div class="alert alert-important alert-danger" role="alert">
                            <div class="d-flex">
                                <div>
                                    One or more server requirements are not met. Please ensure the requirements below are satisfied before proceeding with the installation.
                                </div>
                            </div>
                        </div>
                    @endif

                    <ul class="list-unstyled lh-lg">
                        <div class="mb-2">
                            <li><strong>General Requirements: </strong></li>
                            <li>
                                <!-- Download SVG icon from http://tabler.io/icons/icon/check -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1 text-success icon-2">
                                    <path d="M5 12l5 5l10 -10"></path>
                                </svg>
                                Installed Vaded Hosting Version {{ config('app.version') }}
                            </li>
                            <li>
                                @if(version_compare(PHP_VERSION, $minPhpVersion, '>='))
                                    <!-- Download SVG icon from http://tabler.io/icons/icon/check -->
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1 text-success icon-2">
                                        <path d="M5 12l5 5l10 -10"></path>
                                    </svg>
                                @else
                                    <!-- Download SVG icon from http://tabler.io/icons/icon/x -->
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1 text-danger icon-2">
                                        <path d="M18 6l-12 12"></path>
                                        <path d="M6 6l12 12"></path>
                                    </svg>
                                @endif

                                PHP Version {{ $minPhpVersion }} or higher (current: {{ phpversion() }})
                            </li>
                        </div>
                        <div class="mb-2">
                            <li><strong>Required PHP Extensions: </strong></li>
                            @foreach($requiredExtensions as $extension)
                                <li>
                                    @if(extension_loaded($extension))
                                        <!-- Download SVG icon from http://tabler.io/icons/icon/check -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1 text-success icon-2">
                                            <path d="M5 12l5 5l10 -10"></path>
                                        </svg>
                                    @else
                                        <!-- Download SVG icon from http://tabler.io/icons/icon/x -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1 text-danger icon-2">
                                            <path d="M18 6l-12 12"></path>
                                            <path d="M6 6l12 12"></path>
                                        </svg>
                                    @endif
                                    {{ $extension }}
                                </li>
                            @endforeach
                        </div>
                        <div class="mb-2">
                            <li><strong>Writable Directories: </strong></li>
                            @foreach($writablePaths as $path)
                                <li>
                                    @if(is_writable(base_path($path)))
                                        <!-- Download SVG icon from http://tabler.io/icons/icon/check -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1 text-success icon-2">
                                            <path d="M5 12l5 5l10 -10"></path>
                                        </svg>
                                    @else
                                        <!-- Download SVG icon from http://tabler.io/icons/icon/x -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1 text-danger icon-2">
                                            <path d="M18 6l-12 12"></path>
                                            <path d="M6 6l12 12"></path>
                                        </svg>
                                    @endif
                                    {{ base_path($path) }}
                                </li>
                            @endforeach
                        </div>
                    </ul>
                </div>
            </div>
            <div class="row align-items-center mt-3">
                <div class="col-4">

                </div>
                <div class="col">
                    <div class="btn-list justify-content-end">
                        <button wire:click="changeStep('eula')" onclick="isLoading(this)" class="btn btn-primary btn-2">
                            Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @elseif($step == 'eula')
        <div class="col-12 col-lg-8">
            <div class="card card-md">
                <div class="card-body py-4 p-sm-5">
                    <div class="text-center">
                        <h1>End User License Agreement</h1>
                        <p class="text-secondary">
                            Please read the End User License Agreement carefully before proceeding with the installation. By accepting the terms, you agree to comply with the conditions set forth in the agreement.
                        </p>
                    </div>
                </div>
                <div class="hr-text hr-text-center hr-text-spaceless">End User License Agreement</div>
                <div class="card-body">
                    <div class="markdown p-3 rounded" style="max-height: 400px; overflow-y: auto; background: #6b6b6b1a;">
                        {!! $eulaHtml !!}
                    </div>
                </div>
            </div>
            <div class="row align-items-center mt-3">
                <div class="col-4">
                    <button class="btn btn-2" wire:click="changeStep('requirements')" onclick="isLoading(this)">
                        Previous
                    </button>
                </div>
                <div class="col">
                    <div class="btn-list justify-content-end">
                        <button wire:click="changeStep('activation')" onclick="isLoading(this)" class="btn btn-success btn-2">
                            Accept & Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @elseif($step == 'activation')
            <div class="col-12 col-lg-8">
                <div class="card card-md">
                    <div class="card-body py-4 p-sm-5">
                        <div class="text-center">
                            <h1>{{ \App\Support\LocalLicense::isBypassed() ? (config('app.demo_mode') ? 'Development Demo' : 'Local Development') : 'Activate License Key' }}</h1>
                            <p class="text-secondary">
                                @if(\App\Support\LocalLicense::isBypassed())
                                    License checks are bypassed for this development environment. Continue to configure the database.
                                @else
                                Enter the license key supplied by your application administrator to activate the application.
                                @endif
                            </p>
                        </div>
                    </div>
                    @if(!\App\Support\LocalLicense::isBypassed())
                    <div class="hr-text hr-text-center hr-text-spaceless">License Activation</div>
                    <div class="card-body">
                        @if(!$isLicenseActive && $license_key != '')
                            <div class="alert alert-important alert-danger" role="alert">
                                <div class="d-flex">
                                    <div>
                                        License key is not valid or has expired. Please check your license key and try again.
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="mb-4">
                            <label class="form-label">License Key</label>
                            <div class="input-group">
                                <input type="text" wire:model="license_key" class="form-control" placeholder="WMX-XXXX-XXXX-XXXX-XXXX" autocomplete="off">
                            </div>
                            @error('license_key')
                            <div class="text-danger form-hint">
                                {{ $message }}
                            </div>
                            @else
                            <div class="form-hint">
                                Enter the license key supplied by your application administrator to activate the application.
                            </div>
                            @enderror
                            <div class="mt-2">
                                <button class="btn btn-primary btn-2" wire:click="checkLicense()" onclick="isLoading(this)">
                                    Check License
                                </button>
                            </div>
                        </div>
                        @if($licenseCheckedSuccessfully && $isLicenseActive)
                            <div class="alert alert-important alert-success mb-3" role="alert">
                                <div class="d-flex">
                                    <div>
                                        License checked successfully. You can continue to the next step.
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if($licenseData && $isLicenseActive)
                        <table class="table table-responsive">
                            <thead>
                            <tr>
                                <th class="text-nowrap">Name</th>
                                <th class="text-nowrap">Value</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>License Key</td>
                                <td>{{ $licenseData['license_key'] ?? 'Not Available' }}</td>
                            </tr>
                            <tr>
                                <td>Plan</td>
                                <td>{{ $licenseData['plan_name'] ?? 'Not Available' }} ({{ $licenseData['billing_cycle'] ?? 'Not Available' }})</td>
                            </tr>
                            <tr>
                                <td>Email</td>
                                <td>{{ $licenseData['email'] ?? 'Not Available' }}</td>
                            </tr>
                            <tr>
                                <td>Status</td>
                                <td>{{ $licenseData['status'] ?? 'Not Available' }}</td>
                            </tr>
                            <tr>
                                <td>Expiry</td>
                                <td>{{ $licenseData['expires_at'] ?? 'Never' }}</td>
                            </tr>
                            </tbody>
                        </table>
                        @endif
                    </div>
                    @endif
                </div>
                <div class="row align-items-center mt-3">
                    <div class="col-4">
                        <button class="btn btn-2" wire:click="changeStep('eula')" onclick="isLoading(this)">
                            Previous
                        </button>
                    </div>
                    <div class="col">
                        <div class="btn-list justify-content-end">
                            <button class="btn btn-primary btn-2" wire:click="checkLicenseAndContinue()" onclick="isLoading(this)" @disabled(!$licenseCheckedSuccessfully || !$isLicenseActive)>
                                Continue
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @elseif($step == 'database')
            <div class="col-12 col-lg-8">
                <div class="card card-md">
                    <div class="card-body py-4 p-sm-5">
                        <div class="text-center">
                            <h1>Configure Database</h1>
                            <p class="text-secondary">
                                Select your database driver and provide the required connection details.
                            </p>
                        </div>
                    </div>
                    <div class="hr-text hr-text-center hr-text-spaceless">Configure Database</div>
                    <div class="card-body">
                        @if($isDatabaseDeployed)
                        <div class="alert alert-important alert-success mb-3" role="alert">
                            <div class="d-flex">
                                <div>
                                    Database is already set up and working. You can skip database setup and continue.
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="mb-3">
                            <label class="form-label">Database Driver</label>
                            <div class="input-group">
                                <select class="form-select" wire:model.live="database_connection">
                                    @if($isSqliteAvailable)
                                        <option value="sqlite">SQLite</option>
                                    @endif
                                    <option value="mysql">MySQL</option>
                                    <option value="pgsql">PostgreSQL</option>
                                    <option value="supabase">PostgreSQL (by URL)</option>
                                </select>
                            </div>
                            <div class="form-hint">
                                SQLite is available only when the `pdo_sqlite` extension is installed.
                            </div>
                            @error('database_connection')
                            <div class="text-danger form-hint">{{ $message }}</div>
                            @enderror
                        </div>

                        @if($database_connection === 'sqlite')
                        <div class="mb-3">
                            <label class="form-label">SQLite Database Path</label>
                            <div class="input-group">
                                <input type="text" class="form-control" wire:model="database_path" placeholder="database/database.sqlite">
                            </div>
                            <div class="form-hint">
                                Relative paths are resolved from the project root.
                            </div>
                            @error('database_path')
                            <div class="text-danger form-hint">{{ $message }}</div>
                            @enderror
                        </div>
                        @elseif($database_connection === 'supabase')
                        <div class="mb-3">
                            <label class="form-label">Supabase Database URL</label>
                            <div class="input-group">
                                <input type="text" class="form-control" wire:model="database_url" placeholder="postgres://user:password@host:5432/database?sslmode=require">
                            </div>
                            <div class="form-hint">
                                Paste your full Supabase connection string.
                            </div>
                            @error('database_url')
                            <div class="text-danger form-hint">{{ $message }}</div>
                            @enderror
                        </div>
                        @else
                        <div class="mb-3">
                            <label class="form-label">Database Hostname</label>
                            <div class="input-group">
                                <input type="text" class="form-control" wire:model="database_host" placeholder="127.0.0.1">
                            </div>
                            <div class="form-hint">
                                The hostname of your database server.
                            </div>
                            @error('database_host')
                            <div class="text-danger form-hint">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Port</label>
                            <div class="input-group">
                                <input type="text" class="form-control" wire:model="database_port" placeholder="{{ $database_connection === 'pgsql' ? '5432' : '3306' }}">
                            </div>
                            <div class="form-hint">
                                MySQL default is 3306, PostgreSQL default is 5432.
                            </div>
                            @error('database_port')
                            <div class="text-danger form-hint">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Name</label>
                            <div class="input-group">
                                <input type="text" class="form-control" wire:model="database_name" placeholder="vaded_hosting">
                            </div>
                            <div class="form-hint">
                                The name of the database to migrate.
                            </div>
                            @error('database_name')
                            <div class="text-danger form-hint">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Username</label>
                            <div class="input-group">
                                <input type="text" class="form-control" wire:model="database_username" placeholder="root">
                            </div>
                            @error('database_username')
                            <div class="text-danger form-hint">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" wire:model="database_password" placeholder="">
                            </div>
                            <div class="form-hint">
                                Leave blank if your DB user does not require a password.
                            </div>
                            @error('database_password')
                            <div class="text-danger form-hint">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        <div class="mt-2">
                            <button class="btn btn-primary btn-2" wire:click="testDatabaseConnection()" onclick="isLoading(this)">
                                Test Connection
                            </button>
                        </div>

                        @if($database_test_message)
                        <div class="alert alert-important {{ $database_test_success ? 'alert-success' : 'alert-warning' }} mt-3" role="alert">
                            <div class="d-flex">
                                <div>{{ $database_test_message }}</div>
                            </div>
                        </div>
                        @endif

                        @if($has_existing_database_tables)
                        <div class="alert alert-important alert-warning mt-3" role="alert">
                            <div class="d-flex">
                                <div>
                                    Detected {{ $existing_database_tables_count }} existing application table(s). This does not look like a fresh database.
                                    Confirm below before continuing with migrations.
                                </div>
                            </div>
                        </div>

                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="confirmExistingMigration" wire:model="confirm_migrate_existing_database">
                            <label class="form-check-label" for="confirmExistingMigration">
                                I understand this database already contains tables and I still want to continue migrations.
                            </label>
                        </div>
                        @error('confirm_migrate_existing_database')
                        <div class="text-danger form-hint">{{ $message }}</div>
                        @enderror
                        @endif

                        @if($database_error)
                        <div class="alert alert-important alert-danger mt-3" role="alert">
                            <div class="d-flex">
                                <div>{{ $database_error }}</div>
                            </div>
                        </div>
                        @endif
                        @endif
                    </div>
                </div>
                <div class="row align-items-center mt-3">
                    <div class="col-4">
                        <button class="btn btn-2" wire:click="changeStep('activation')" onclick="isLoading(this)">
                            Previous
                        </button>
                    </div>
                    <div class="col">
                        <div class="btn-list justify-content-end">
                            <button class="btn btn-primary btn-2" wire:click="checkDatabaseAndContinue()" onclick="isLoading(this)" @disabled(!$database_test_success)>
                                Continue
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @elseif($step == 'setup')
            <div class="col-12 col-lg-8">
                <div class="card card-md">
                    <div class="card-body py-4 p-sm-5">
                        <div class="text-center">
                            <h1>Configure Application</h1>
                            <p class="text-secondary">
                                Please provide the necessary configuration details for your application. This includes the application name, URL, and admin credentials.
                            </p>
                        </div>
                    </div>
                    <div class="hr-text hr-text-center hr-text-spaceless">Application Settings</div>
                    <div class="card-body">
                        <form wire:submit="saveChanges">
                            <div class="card-body">
                                <div class="mb-4">
                                    <h3 class="card-title">Application Name</h3>
                                    <p class="card-subtitle">
                                        Your application's name displayed to users.
                                    </p>
                                    <div class="row g-2">
                                        <div class="col">
                                            <x-admin::form.input wire:model="app_name" label="Application Name" name="application_name" placeholder="Application Name" />
                                        </div>
                                        @error('app_name')
                                        <x-admin::form.error :message="$message" />
                                        @enderror
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <h3 class="card-title">Default Language</h3>
                                    <p class="card-subtitle">
                                        The default language of the application.
                                    </p>
                                    <div class="row g-2">
                                        <div class="col">
                                            <x-admin::form.select wire:model="language" id="language" value="{{ settings('language', 'en') }}" :options="$languages" searchable />
                                        </div>
                                        @error('language')
                                        <x-admin::form.error :message="$message" />
                                        @enderror
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <h3 class="card-title">Default Currency</h3>
                                    <p class="card-subtitle">
                                        The default currency for the application. You can manage currencies in the <a href="{{ route('admin.currencies.index') }}">currencies</a> section.
                                    </p>
                                    <div class="row g-2">
                                        <div class="col">
                                            <x-admin::form.select wire:model="currency" value="{{ settings('currency', 'USD') }}" id="currency" :options="\App\Models\Currency::pluck('display_name', 'currency')" searchable />
                                        </div>
                                        @error('currency')
                                        <x-admin::form.error :message="$message" />
                                        @enderror
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <h3 class="card-title">Default Timezone</h3>
                                    <p class="card-subtitle">
                                        The default timezone for the application. This will be used for date and time formatting.
                                    </p>
                                    <div class="row g-2">
                                        <div class="col">
                                            <x-admin::form.select wire:model="timezone" value="{{ settings('timezone', 'UTC') }}" id="timezone" :options="array_combine(timezone_identifiers_list(), timezone_identifiers_list())" searchable />
                                        </div>
                                        @error('timezone')
                                        <x-admin::form.error :message="$message" />
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="row align-items-center mt-3">
                    <div class="col-4">
                        <button class="btn btn-2" wire:click="changeStep('database')" onclick="isLoading(this)">
                            Previous
                        </button>
                    </div>
                    <div class="col">
                        <div class="btn-list justify-content-end">
                            <button class="btn btn-primary btn-2" wire:click="saveAppSettingsAndContinue()" onclick="isLoading(this)">
                                Continue
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @elseif($step == 'create_user')
            <div class="col-12 col-lg-8">
                <div class="card card-md">
                    <div class="card-body py-4 p-sm-5">
                        <div class="text-center">
                            <h1>Create Administrator Account</h1>
                            <p class="text-secondary">
                                Please create an administrator account to manage your application. This account will have full access to all features and settings of the application.
                            </p>
                        </div>
                    </div>
                    <div class="hr-text hr-text-center hr-text-spaceless">Configure Database</div>
                    <div class="card-body">
                        @if($hasAdminUser)
                        <div class="alert alert-important alert-success" role="alert">
                            <div class="d-flex">
                                <div>
                                    An administrator account already exists. You can log in with the existing credentials.
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" wire:model="first_name" placeholder="First Name">
                                    </div>
                                    @error('first_name')
                                    <div class="text-danger form-hint">
                                        {{ $message }}
                                    </div>
                                    @else
                                    <div class="form-hint">
                                        The first name of the administrator.
                                    </div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" wire:model="last_name" placeholder="Last Name">
                                    </div>
                                    @error('last_name')
                                    <div class="text-danger form-hint">
                                        {{ $message }}
                                    </div>
                                    @else
                                    <div class="form-hint">
                                        The last name of the administrator.
                                    </div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <div class="input-group">
                                <input type="text" class="form-control" wire:model.blur="username" placeholder="Username">
                            </div>
                            @error('username')
                            <div class="text-danger form-hint">
                                {{ $message }}
                            </div>
                            @else
                            <div class="form-hint">
                                The username for the administrator account.
                            </div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <div class="input-group">
                                <input type="email" class="form-control" wire:model.blur="email" placeholder="Email">
                            </div>
                            @error('email')
                            <div class="text-danger form-hint">
                                {{ $message }}
                            </div>
                            @else
                            <div class="form-hint">
                                The email of the administrator.
                            </div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="{{ $show_password ? 'text' : 'password' }}" class="form-control" wire:model.live.debounce.400ms="password" placeholder="Password">
                                <button class="btn btn-outline-secondary" type="button" wire:click="$toggle('show_password')">
                                    {{ $show_password ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar {{ $this->passwordStrengthScore >= 75 ? 'bg-success' : ($this->passwordStrengthScore >= 50 ? 'bg-warning' : 'bg-danger') }}" role="progressbar" style="width: {{ $this->passwordStrengthScore }}%"></div>
                            </div>
                            <div class="form-hint">
                                Password strength: {{ $this->passwordStrengthScore }}%
                            </div>
                            @error('password')
                            <div class="text-danger form-hint">
                                {{ $message }}
                            </div>
                            @else
                            <div class="form-hint">
                                The password for the administrator account. Ensure that it is strong and secure.
                            </div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="{{ $show_password_confirmation ? 'text' : 'password' }}" class="form-control" wire:model.live.debounce.400ms="password_confirmation" placeholder="Confirm Password">
                                <button class="btn btn-outline-secondary" type="button" wire:click="$toggle('show_password_confirmation')">
                                    {{ $show_password_confirmation ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                            @error('password_confirmation')
                            <div class="text-danger form-hint">
                                {{ $message }}
                            </div>
                            @else
                            <div class="form-hint">
                                The password for the administrator account. Ensure that it is strong and secure.
                            </div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary" wire:click="generateSecurePassword()">
                                Generate Secure Password
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="row align-items-center mt-3">
                    <div class="col-4">
                        <button class="btn btn-2" wire:click="changeStep('setup')" onclick="isLoading(this)">
                            Previous
                        </button>
                    </div>
                    <div class="col">
                        <div class="btn-list justify-content-end">
                            <button class="btn btn-primary btn-2" wire:click="createAdminUserAndContinue()" onclick="isLoading(this)">
                                Continue
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @elseif($step == 'complete')
            <div class="col-12 col-lg-8">
                <div class="card card-md">
                    <div class="card-body py-4 p-sm-5">
                        <div class="text-center">
                            <h1>Vaded Hosting was installed successfully</h1>
                            <p class="text-secondary">
                                Your application is ready to use! You can now log in with the administrator account you created. If you have any questions or need assistance, please refer to our documentation or contact support.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="row align-items-center mt-3">
                    <div class="col-4">
                        <button class="btn btn-2" wire:click="changeStep('create_user')" onclick="isLoading(this)">
                            Previous
                        </button>
                    </div>
                    <div class="col">
                        <div class="btn-list justify-content-end">
                            <a class="btn btn-primary btn-2" wire:click="finish" onclick="isLoading(this)">
                                Finish Installation
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
