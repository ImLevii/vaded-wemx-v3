<?php

namespace Database\Seeders;

use App\Models\KnowledgebaseArticle;
use App\Models\KnowledgebaseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KnowledgebaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'getting-started' => ['name' => 'Getting started', 'description' => 'Your first service, the client area, and finding the right control panel.', 'icon' => 'console', 'sort_order' => 0],
            'minecraft' => ['name' => 'Minecraft', 'description' => 'Connect, customize, and troubleshoot your Minecraft server.', 'icon' => 'cube', 'sort_order' => 1],
            'game-servers' => ['name' => 'Game servers', 'description' => 'Server setup, configuration, files, and backups for your community.', 'icon' => 'server', 'sort_order' => 2],
            'vps-hosting' => ['name' => 'VPS hosting', 'description' => 'Connect to your virtual server and manage your applications.', 'icon' => 'cloud', 'sort_order' => 3],
            'discord-bots' => ['name' => 'Discord bots', 'description' => 'Get your bot online, manage dependencies, and protect its token.', 'icon' => 'bot', 'sort_order' => 4],
            'billing-and-account' => ['name' => 'Billing & account', 'description' => 'Payments, renewals, account security, and service access.', 'icon' => 'receipt', 'sort_order' => 5],
        ];

        foreach ($categories as $slug => $attributes) {
            KnowledgebaseCategory::query()->firstOrCreate(['slug' => $slug], $attributes);
        }

        foreach ($this->articles() as $index => $article) {
            $category = KnowledgebaseCategory::query()->where('slug', $article['category'])->firstOrFail();
            KnowledgebaseArticle::query()->firstOrCreate(
                ['slug' => Str::slug($article['title'])],
                [
                    'knowledgebase_category_id' => $category->id,
                    'title' => $article['title'],
                    'summary' => $article['summary'],
                    'content' => $article['content'],
                    'is_published' => true,
                    'is_featured' => $index % 4 === 0,
                    'sort_order' => $index % 4,
                ]
            );
        }
    }

    /** @return list<array{category: string, title: string, summary: string, content: string}> */
    private function articles(): array
    {
        return [
            [
                'category' => 'getting-started',
                'title' => 'Find and manage your hosting service',
                'summary' => 'Locate your order, service details, and available controls in the Vaded client area.',
                'content' => <<<'MARKDOWN'
## Open your client area

Sign in to the Vaded website with the account you used at checkout. Open **Client area** in the navigation, then choose your service from the dashboard.

If you cannot find an order, check that you are using the correct email address. A service invitation may need to be accepted before a shared service appears.

## Check your service details

Your service page brings together its status, billing, and any connection information provided by the hosting integration. Look for the server address, assigned port, and control-panel link.

A pending service may still be provisioning or awaiting payment. Check its payment status before trying to connect.

## Open the hosting panel

Use the panel link shown for your service, if one is available. The hosting panel and the Vaded billing area may use separate credentials. Use the credentials issued for that panel rather than repeatedly trying your billing password.

Never share service passwords in public chat. If a panel link or connection detail is missing after provisioning completes, contact support with your order number.
MARKDOWN,
            ],
            [
                'category' => 'getting-started',
                'title' => 'What to do after placing an order',
                'summary' => 'Check payment and provisioning before connecting to your new service.',
                'content' => <<<'MARKDOWN'
## Confirm your payment

After checkout, open your payments in the client area and confirm that the payment is marked as paid. A payment-provider receipt alone does not confirm that the service has finished provisioning.

If you paid but the payment remains pending, save the transaction reference and contact support. Avoid paying the same invoice again while it is being investigated.

## Wait for the service to be ready

Return to the dashboard and open the order. Provisioning time depends on the service and available capacity. Refresh the service page to check its current status.

Use the connection details displayed for the service once it is ready. Keep the port with the address when the game or application requires one.

## Save your details securely

Store panel credentials in a password manager. Change an initial password if the panel supports it, and keep your billing email current.

Before uploading files or changing software, review the guide for your hosting product. Make a backup before replacing an existing world or application.
MARKDOWN,
            ],
            [
                'category' => 'getting-started',
                'title' => 'Transfer files using SFTP',
                'summary' => 'Use your service\'s SFTP details to upload and download server files securely.',
                'content' => <<<'MARKDOWN'
## Find your SFTP details

If your service offers SFTP, open its hosting panel and locate the SFTP address, port, and username. These can differ from your game-server address and port.

Use an SFTP client that supports SSH file transfers. Select **SFTP**, not FTP, and enter the exact details provided by the panel.

## Connect securely

On your first connection, verify the server's host key against a trusted fingerprint if one is supplied. A changed host key deserves investigation before you enter a password.

Authenticate using the panel's documented password or SSH key method. Do not post these details in screenshots or support logs.

## Upload your files

Stop your game server before replacing worlds, plugins, or core files. Download a backup of the files you plan to change, then upload into the correct directory.

Keep filenames and folder structure intact. Start the server afterward and check its console for errors.

## If the connection fails

Confirm the SFTP port and username, check for an extra space in copied values, and verify that SFTP is enabled for your service. Your game port is not an SFTP port.
MARKDOWN,
            ],
            [
                'category' => 'getting-started',
                'title' => 'Prepare useful information for a support request',
                'summary' => 'Gather the details that help support diagnose a problem quickly.',
                'content' => <<<'MARKDOWN'
## Describe the problem

Include your service name or order number, the game or application involved, and what you expected to happen. Describe what actually happened and the approximate time, including your timezone.

Tell support whether the issue affects everyone or only one player or device.

## Include the steps to reproduce it

List the actions that trigger the problem. Mention recent changes, such as a new plugin, software update, configuration edit, or world upload.

Copy the relevant console error as text where possible. Include nearby lines so support can see the context, but avoid sending an entire unrelated log.

## Remove secrets

Redact passwords, Discord bot tokens, API keys, private keys, and payment details. Share connection information only through the support channels linked by Vaded.

If you accidentally exposed a token or password, rotate it before continuing.

## Check existing guides

Search this knowledgebase using the error message or feature name. If a guide did not resolve the issue, mention the steps you already tried so support can continue from there.
MARKDOWN,
            ],
            [
                'category' => 'minecraft',
                'title' => 'Connect to your Minecraft server',
                'summary' => 'Find your server address and join using the matching Minecraft edition and version.',
                'content' => <<<'MARKDOWN'
## Check your edition and version

Minecraft Java Edition and Bedrock Edition use different connection methods. Use the edition supported by your server. Match the client version to the server version unless you have deliberately configured compatibility software.

Start the server in its hosting panel and wait for the console to report that startup has completed.

## Join a Java server

In Minecraft Java Edition, open **Multiplayer**, choose **Add Server**, and enter the address shown in your hosting panel. Include the assigned port when it is not the default:

```text
your-server-address:your-port
```

The example is a format, not a working Vaded address.

## Join a Bedrock server

In Bedrock Edition, open the server list and add a server where your platform supports it. Enter the hostname or IP address and the port in their separate fields.

## If you cannot join

Check that the service is running and that you copied the entire address. An incompatible-version error usually means the client and server versions differ. A whitelist error means your account needs to be added by a server administrator.
MARKDOWN,
            ],
            [
                'category' => 'minecraft',
                'title' => 'Install Minecraft plugins',
                'summary' => 'Add compatible plugins to a server that supports the Bukkit plugin API.',
                'content' => <<<'MARKDOWN'
## Check your server software

Plugins require compatible server software, such as Paper or another implementation of the Bukkit API. A vanilla Minecraft server does not load Bukkit plugins. Fabric and Forge use mods rather than Bukkit plugins.

Check both the Minecraft version and the server software supported by the plugin.

## Back up and stop the server

Create a backup before installing new plugins. Stop the server completely before uploading or removing plugin files.

Download the plugin from its trusted publisher. Review any required dependencies and avoid running unknown JAR files.

## Install and restart

Upload the plugin's JAR file to the **plugins** folder using the panel file manager or SFTP. Install its required dependencies there as well.

Start the server and read the console. Many plugins create configuration folders on their first successful startup.

## Configure the plugin

Stop the server again before editing configuration files, unless the plugin documents a safe reload method. Preserve YAML indentation and restart to apply the changes.

If the plugin fails to load, check the console for a version mismatch, missing dependency, or configuration error. Restore your backup if the new plugin prevents startup.
MARKDOWN,
            ],
            [
                'category' => 'minecraft',
                'title' => 'Upload an existing Minecraft world',
                'summary' => 'Replace your world safely while preserving a copy of the current server.',
                'content' => <<<'MARKDOWN'
## Prepare a backup

Download a copy of the existing world and configuration before changing anything. Stop the server and wait until it is fully offline.

Check the Minecraft version used to create the world. Opening a world in a newer version can change it in ways that prevent a safe downgrade.

## Upload the world folder

Extract the world archive on your computer. Upload the world folder using the panel file manager or SFTP. Make sure the folder itself contains the world files rather than another nested copy of the folder.

For a Java world, look for files such as **level.dat** and the **region** directory.

## Select the world

For a Java server, open **server.properties** and set **level-name** to the exact folder name of your world. Bedrock and some server implementations store worlds differently; use their documented world setting.

Preserve any Nether and End folders required by your server software.

## Start and verify

Start the server and check the console. Join the server to confirm the correct world loaded.

If the server creates a new empty world, stop it and check the configured folder name and upload structure before trying again.
MARKDOWN,
            ],
            [
                'category' => 'minecraft',
                'title' => 'Troubleshoot Minecraft lag and startup errors',
                'summary' => 'Use console logs and server behavior to narrow down performance and startup problems.',
                'content' => <<<'MARKDOWN'
## Identify the symptom

Server lag affects game simulation, while a low client frame rate affects rendering on your device. A connection delay or disconnect can also come from a player's network.

Check whether all players are affected. Note the time of the problem and read the server console for errors.

## Check recent changes

If startup stopped working after installing a mod or plugin, back up the files and temporarily remove the latest addition. Check that the server version, Java version, and any dependencies match its requirements.

For a configuration error, restore the last working configuration or fix the exact line identified by the console.

## Reduce unnecessary load

A large view distance, heavy world generation, and many active entities can increase server work. Change one setting at a time and compare the result.

Use a profiler supported by your server software to investigate slow ticks. Adding memory alone does not resolve every performance problem.

## Ask for help with evidence

Include the service name, server software and version, relevant console output, and what changed recently. Do not delete your world to troubleshoot lag. Keep a backup before testing changes.
MARKDOWN,
            ],
            [
                'category' => 'game-servers',
                'title' => 'Start, stop, and restart your game server',
                'summary' => 'Use the hosting panel and console to manage your server safely.',
                'content' => <<<'MARKDOWN'
## Open the correct panel

Find your service in the Vaded client area and follow its hosting-panel link. Select the server you want to manage.

The controls available depend on the game's hosting integration. The panel normally shows the current status and a console or log viewer.

## Start the server

Choose the start control and follow the console output. Wait until the game reports that it is ready before trying to join.

Repeatedly pressing start will not resolve a startup error. Read the first useful error in the log and check the related configuration or missing file.

## Stop safely

Use the normal stop control so the game can save its data. Wait for the server to become fully offline before replacing files or making a backup.

Use a forced shutdown only when a normal stop cannot complete; it can interrupt saves.

## Restart after changes

Make one configuration change at a time and restart when the game requires it. Verify the change in the console and connect to confirm it worked.

If the server fails after a change, restore the last working file from your backup.
MARKDOWN,
            ],
            [
                'category' => 'game-servers',
                'title' => 'Create and restore game-server backups',
                'summary' => 'Protect saves and configuration before updates, file changes, or migrations.',
                'content' => <<<'MARKDOWN'
## Decide what to protect

Back up saves or worlds, game configuration, plugins or mods, and any data needed to recreate your setup. A copy on the same service should not be your only backup.

Check whether your hosting panel includes a backup tool. Available backup storage and limits depend on the service.

## Create a consistent backup

Ask players to disconnect and stop the server normally. Wait for saves to finish before making the backup.

Use the panel's backup function if available, or download the relevant folders over SFTP. Give the backup a name that includes the date and game version.

Check that the resulting archive or downloaded files are complete.

## Restore carefully

Restoring a backup can replace current files and remove progress made afterward. Download a separate copy of the current state before you proceed.

Stop the server, restore the chosen files, and check the configured save path. Match the software version expected by the backup.

## Test the result

Start the server and review the console. Join to confirm that the expected world, saves, and settings are present.

Keep the previous backup until you have verified the restored server.
MARKDOWN,
            ],
            [
                'category' => 'game-servers',
                'title' => 'Edit game-server configuration files',
                'summary' => 'Change settings without losing your working configuration.',
                'content' => <<<'MARKDOWN'
## Find the right configuration

Open your server in its hosting panel. Check the game's documentation for the configuration file and setting you want to change.

Some settings are controlled by startup parameters or panel variables rather than a file. Editing a generated file may have no effect if the panel overwrites it at startup.

## Save a working copy

Stop the server when the game requires an offline edit. Download a backup of the configuration before changing it.

Use a plain-text editor. Preserve the file format: YAML relies on indentation, JSON requires valid commas and quotes, and INI-style files usually use key-value pairs.

## Change one setting

Edit only the value you need and keep the filename unchanged. Avoid copying configuration examples for a different game version.

Save the file and restart the server when required.

## Verify or roll back

Read the console for parsing errors and test the behavior in-game. If the setting is ignored, check its documented location and whether a startup option overrides it.

Restore the working copy if the server cannot start, then investigate the exact error before trying again.
MARKDOWN,
            ],
            [
                'category' => 'game-servers',
                'title' => 'Troubleshoot a game-server connection',
                'summary' => 'Check the address, game version, and running state before changing files.',
                'content' => <<<'MARKDOWN'
## Check the service status

Open your hosting panel and confirm that the server is running. Read the console to verify it finished startup and did not stop after reporting an error.

If the service is suspended or awaiting payment, resolve that status in the client area first.

## Confirm the address

Copy the server IP or hostname and the correct game port. Some games use separate query and connection ports; connect with the port documented for players.

Do not substitute an SFTP or control-panel port.

## Check the game client

Match the game version and any required mods. Check whether a password, allowlist, or access restriction is enabled.

Try connecting from another device or network where possible. If other players can join, the issue may be local to your connection or game client.

## Collect useful evidence

Record the exact client error, the time it happened, and nearby server-console output. Check any network-status link provided by Vaded.

Send support the service name and these details. Avoid changing multiple ports or reinstalling the game server without a backup.
MARKDOWN,
            ],
            [
                'category' => 'vps-hosting',
                'title' => 'Connect to your VPS using SSH',
                'summary' => 'Use the IP address and credentials supplied with your Linux VPS.',
                'content' => <<<'MARKDOWN'
## Find your connection details

Open the VPS service in your Vaded client area. Find its IP address and the username, password, or SSH-key instructions supplied for the installed operating system.

Do not assume every image uses the same username or SSH port.

## Open an SSH connection

On a computer with an SSH client, replace the example values with your own details:

```bash
ssh username@your-vps-ip
```

If the service uses a custom SSH port, supply it with the **-p** option.

On the first connection, verify the host-key fingerprint through a trusted source if one is available. Do not ignore an unexpected changed-key warning.

## Authenticate

Use the password or private key associated with the VPS. Password entry in a terminal usually does not display characters as you type.

Keep private keys and credentials secret.

## If you cannot connect

Check that the VPS is powered on and the address is correct. A timeout can indicate a network or firewall problem; an authentication failure points to credentials or the wrong username.

If available, use the provider console to inspect the VPS without relying on SSH. Contact support if you cannot access either method.
MARKDOWN,
            ],
            [
                'category' => 'vps-hosting',
                'title' => 'Secure a new Linux VPS',
                'summary' => 'Set up careful access controls before exposing applications to the internet.',
                'content' => <<<'MARKDOWN'
## Update the operating system

Use your distribution's package manager to install current security updates. Read the distribution documentation before upgrading between major releases.

Plan a reboot when an update requires it, and check your applications afterward.

## Set up your administrative access

Create a separate administrative user using your distribution's documented procedure. Add an SSH public key and test a fresh login before changing existing access settings.

Keep a recovery method, such as the provider console, available.

## Configure the firewall

Identify the ports your applications require. Allow the current SSH port before enabling or tightening a firewall, then test a second connection while keeping the first session open.

Avoid exposing databases or internal administration ports publicly unless you have a specific, secured reason.

## Protect credentials and data

Use unique passwords and keep API keys out of public repositories. Maintain off-server backups and install only software you trust.

Disabling password login or restricting privileged access can improve security, but apply these changes only after you have verified key-based access and a recovery path.
MARKDOWN,
            ],
            [
                'category' => 'vps-hosting',
                'title' => 'Point a domain at your VPS',
                'summary' => 'Create DNS records for your VPS and verify that your application is listening.',
                'content' => <<<'MARKDOWN'
## Get the public address

Find your VPS public IP address in the service details. Confirm whether the application needs IPv4, IPv6, or both.

Open the DNS management page for your domain at its DNS provider. This is usually separate from Vaded's client area.

## Add the DNS records

Create an **A** record pointing the hostname to the VPS IPv4 address. Use an **AAAA** record only when you have working public IPv6 on the VPS.

A subdomain such as **app.example.com** needs a record for that subdomain. Remove conflicting records only after checking what uses them.

## Configure the application

DNS maps a name to an address; it does not open a firewall port or configure a web server. Make sure your application listens on the intended interface and port.

For a website, configure the hostname in your web server or reverse proxy and install a valid TLS certificate.

## Verify the change

Allow time for DNS caches to expire. Check the hostname with a DNS lookup and compare the returned address to your VPS.

If DNS is correct but the site is unreachable, inspect the application, firewall, and web-server logs.
MARKDOWN,
            ],
            [
                'category' => 'vps-hosting',
                'title' => 'Troubleshoot an unreachable VPS',
                'summary' => 'Separate a network problem from an operating-system or application problem.',
                'content' => <<<'MARKDOWN'
## Identify what stopped working

Check whether only a website or game is unreachable, or whether SSH is also failing. Note the exact error and when the problem began.

Verify the service status in your Vaded client area. Check the public address rather than relying on an old saved connection.

## Use the console if available

If your service provides a console, use it to inspect the operating system. A working console with failing SSH can point to an SSH service, network configuration, or firewall problem.

Look for full disk space, failed services, or resource exhaustion. Avoid repeatedly rebooting before you collect the error information.

## Review recent changes

A changed SSH port, firewall rule, DNS record, or application update can affect access. Restore a known working configuration when you have a safe recovery path.

If only an application is failing, check its service status and logs.

## Contact support

Include the service name, affected address, time of failure, and whether the console works. Use any linked network-status page to check for a reported incident.

Reinstalling an operating system can erase data. Make backups and resolve the cause before considering a reinstall.
MARKDOWN,
            ],
            [
                'category' => 'discord-bots',
                'title' => 'Deploy your Discord bot',
                'summary' => 'Upload your application and configure the runtime and startup command.',
                'content' => <<<'MARKDOWN'
## Prepare your bot

Check whether your hosting plan supports the runtime your bot uses, such as Node.js or Python. Keep a working copy of the code on your computer or in a private repository.

Your application needs its source files and a dependency manifest, such as **package.json** or **requirements.txt**.

## Upload the application

Open your bot service's hosting panel and upload the project into the application directory. Keep the folder structure expected by the entry point.

Do not upload a local environment file containing tokens into a public repository.

## Configure startup

Set the startup file or command using the controls available for your service. Match the runtime version to your application and install the dependencies through the supported workflow.

Provide the bot token through a private environment variable or the panel's secret-setting mechanism where available.

## Start and check the logs

Start the service and inspect its console. Verify that the bot logs in successfully and appears online in Discord.

If startup fails, check the missing-module message, entry-point path, runtime version, or environment-variable name identified by the log.
MARKDOWN,
            ],
            [
                'category' => 'discord-bots',
                'title' => 'Keep your Discord bot token private',
                'summary' => 'Store tokens securely and rotate them immediately after accidental exposure.',
                'content' => <<<'MARKDOWN'
## Treat the token as a password

A Discord bot token grants access to the bot account. Anyone who obtains it may be able to act as your bot.

Keep it out of public chat, screenshots, logs, and source-code repositories. Load it from a private environment variable or the secret mechanism supported by your hosting panel.

## If the token is exposed

Reset the bot token in the Discord Developer Portal for the correct application. This invalidates the old token.

Update the private token setting in your hosting environment and restart the bot. Check that it authenticates successfully with the new token.

## Remove exposed copies

Remove the token from current source files and logs. A token removed from a Git file can still exist in repository history, so rotation is essential.

Review your bot's activity and application access if you suspect misuse.

## Ask for help safely

Share the authentication error and relevant code with secret values replaced by placeholders. Support does not need your live token to diagnose a missing environment variable or incorrect startup command.
MARKDOWN,
            ],
            [
                'category' => 'discord-bots',
                'title' => 'Install and update bot dependencies',
                'summary' => 'Use a dependency manifest and compatible runtime to keep your bot reproducible.',
                'content' => <<<'MARKDOWN'
## Check the runtime

Confirm the runtime and version supported by your bot's hosting service. Use a version supported by both your code and its libraries.

Back up your application before changing dependencies.

## Use the project manifest

For a Node.js bot, include **package.json** and its lockfile when available. For a Python bot, include its dependency list, such as **requirements.txt**.

Use the install workflow documented by the hosting panel. If you have a terminal, run the package-manager command from the directory containing the manifest.

Avoid uploading a dependency folder built for a different operating system.

## Update deliberately

Review library release notes before making a major-version update. Update a small set of dependencies at a time and test the bot in a private or test environment where possible.

Keep a copy of the previous manifest and lockfile.

## Troubleshoot startup

A missing-module error can indicate that installation failed or happened in the wrong directory. A syntax or unsupported-feature error can indicate a runtime mismatch.

Read the complete install error before retrying, and restore your working dependency versions if necessary.
MARKDOWN,
            ],
            [
                'category' => 'discord-bots',
                'title' => 'Troubleshoot an offline Discord bot',
                'summary' => 'Use the hosting console to check startup, authentication, and permissions.',
                'content' => <<<'MARKDOWN'
## Check the hosting service

Confirm that the bot service is running in its hosting panel. Read the startup log: a service process can be running while the bot is unable to authenticate.

Check the entry-point file, startup command, runtime, and dependency installation.

## Check authentication

An invalid-token error means the configured token is missing, outdated, or incorrect. Check that the application reads the same environment-variable name you configured.

Rotate an exposed token before updating the hosting setting. Never post the token when requesting support.

## Check Discord settings

Verify that the bot is invited to the intended server and has the permissions needed for its features.

Some features require gateway intents configured in both your code and the Discord Developer Portal. Follow the documentation for your current bot library and Discord application settings.

## Avoid duplicate processes

Running multiple copies of the same bot can cause confusing behavior. Stop duplicate instances before testing.

If the bot still fails, send support the relevant error and runtime version, with secrets removed. If it is online but does not respond, investigate command registration and permissions rather than repeatedly restarting.
MARKDOWN,
            ],
            [
                'category' => 'billing-and-account',
                'title' => 'Find your payments and invoices',
                'summary' => 'Review payment status and download available invoices from your client area.',
                'content' => <<<'MARKDOWN'
## Open your payments

Sign in to Vaded and open **Payments & invoices** from the client dashboard. Find the payment for the service or renewal you want to review.

Check the amount, currency, description, and status before completing a payment.

## View an invoice

Open the payment or invoice details. Use the invoice download option when one is available.

An unpaid invoice or pending payment is not proof that a charge completed. Compare its status with the payment-provider receipt if you are investigating a discrepancy.

## If a payment is missing

Confirm that you are signed in to the account used for the order. Keep the payment-provider transaction reference, payment time, amount, and currency.

Contact support with those details and the related order number. Never send full card details or your payment-account password.

## Prevent duplicate payments

Do not pay an invoice again solely because a payment is taking time to update. Check its status and ask support to investigate a confirmed provider charge.

Subscription payments and manual invoice payments can follow different workflows, so review any existing subscription before paying a renewal manually.
MARKDOWN,
            ],
            [
                'category' => 'billing-and-account',
                'title' => 'Understand renewals and subscriptions',
                'summary' => 'Check your next due date and the payment method associated with your service.',
                'content' => <<<'MARKDOWN'
## Review your service billing

Open the service from the client dashboard and review its billing details and next due date. The renewal period is the period selected for that service.

Check your payments for any outstanding renewal invoice.

## Check automatic payments

Open **Subscriptions** to review payment subscriptions linked to your account. If a service offers balance renewal, check that setting on the service and make sure your wallet has sufficient funds.

Do not assume that placing an order automatically enables recurring payments.

## Change a subscription

Use the subscription controls available in your client area. Canceling a payment subscription stops that subscription's automatic charges; it does not by itself confirm a service-cancellation request.

Review the service status and current terms separately if you want to stop hosting.

## Avoid an overdue service

Keep your billing email current and check renewal notices. If a payment fails, resolve the invoice or contact support before the due date.

Suspension, retention, and cancellation conditions depend on the service and current terms. Keep your own backups rather than relying on a service remaining available after its billing period ends.
MARKDOWN,
            ],
            [
                'category' => 'billing-and-account',
                'title' => 'Secure your Vaded account with two-factor authentication',
                'summary' => 'Add an authenticator and store your recovery details safely.',
                'content' => <<<'MARKDOWN'
## Open account settings

Sign in to your Vaded client area and open **Account settings**. Find the two-factor authentication controls.

Use a trusted authenticator application on a device you control.

## Enable two-factor authentication

Start the setup flow and add the displayed QR code or setup secret to your authenticator. Enter the generated verification code to complete the process.

Keep the setup secret private. If recovery codes are provided, save them securely outside the device that stores your authenticator.

## Protect your account

Use a unique password and keep access to your account email secure. Do not share your account login to give someone access to a service; use service invitations where available.

Check that your authenticator device's clock is accurate if a current code is rejected.

## If you lose access

Use the recovery method supplied during setup. If you cannot recover the account, contact support through the channels published by Vaded.

Support may need to verify account ownership before changing security settings. Do not send passwords, authenticator secrets, or recovery codes in a public channel.
MARKDOWN,
            ],
            [
                'category' => 'billing-and-account',
                'title' => 'Invite someone to manage your service',
                'summary' => 'Use service membership instead of sharing your billing login.',
                'content' => <<<'MARKDOWN'
## Open service membership

Find the service in your client dashboard and open its **Members** page. The actions available depend on your access to that service.

Use the invitation control to invite the person using the account information requested by the form.

## Have them accept the invitation

The invited person should sign in to their own Vaded account and review **Service invitations** in the client area.

They need to accept the invitation before they can access the shared service. If it does not appear, confirm that you invited the correct account.

## Review access carefully

Only invite people you trust to manage the service. Review the controls available to members rather than assuming their access is limited to a single task.

A billing-area invitation does not necessarily create a separate account in an external hosting panel. Follow that panel's own access controls where needed.

## Remove access when needed

Remove membership when someone no longer needs access. Rotate any server or panel passwords you previously shared with them.

Keep your personal Vaded password private and use two-factor authentication on your own account.
MARKDOWN,
            ],
        ];
    }
}
