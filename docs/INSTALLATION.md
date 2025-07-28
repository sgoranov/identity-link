# Installation Guide

<!-- TOC -->
* [Installation Guide](#installation-guide)
  * [Prerequisites](#prerequisites)
    * [The following PHP extensions are also required:](#the-following-php-extensions-are-also-required)
  * [Database Setup (PostgreSQL)](#database-setup-postgresql)
  * [Create Project Directories](#create-project-directories)
  * [Apache Virtual Host Configuration](#apache-virtual-host-configuration)
    * [HTTPS + HTTP Redirect Setup](#https--http-redirect-setup)
    * [Enable Required Apache Modules](#enable-required-apache-modules)
    * [Enable the Virtual Host and Reload Apache](#enable-the-virtual-host-and-reload-apache)
  * [Setup _users_ service](#setup-_users_-service-)
    * [Clone the Repository](#clone-the-repository)
    * [Move Into the Service Directory](#move-into-the-service-directory)
    * [Install PHP Dependencies](#install-php-dependencies)
    * [Configure the Service](#configure-the-service)
    * [Run Database Migrations](#run-database-migrations)
    * [Test the Service](#test-the-service)
  * [Setup _clients_ service](#setup-_clients_-service)
    * [Clone the Repository](#clone-the-repository-1)
    * [Move Into the Service Directory](#move-into-the-service-directory-1)
    * [Install PHP Dependencies](#install-php-dependencies-1)
    * [Configure the Service](#configure-the-service-1)
    * [Run Database Migrations](#run-database-migrations-1)
    * [Test the Service](#test-the-service-1)
  * [Setup _2FA_ service](#setup-_2fa_-service)
    * [Clone the Repository](#clone-the-repository-2)
    * [Move Into the Service Directory](#move-into-the-service-directory-2)
    * [Install PHP Dependencies](#install-php-dependencies-2)
    * [Configure the Service](#configure-the-service-2)
    * [Run Database Migrations](#run-database-migrations-2)
    * [Test the Service](#test-the-service-2)
  * [Setup _core_ service](#setup-_core_-service)
    * [Clone the Repository](#clone-the-repository-3)
    * [Move Into the Service Directory](#move-into-the-service-directory-3)
    * [Install PHP Dependencies](#install-php-dependencies-3)
    * [Configure the Service](#configure-the-service-3)
    * [Run Database Migrations](#run-database-migrations-3)
    * [Generate Keys](#generate-keys)
    * [Test the Service](#test-the-service-3)
  * [Generate Data](#generate-data)
    * [Generate Client Data](#generate-client-data)
      * [Step 1: Create a Client Group](#step-1-create-a-client-group)
      * [Step 2: Create the Client](#step-2-create-the-client)
      * [Step 3: Create the Client Secret](#step-3-create-the-client-secret)
    * [Generate User Data](#generate-user-data)
      * [Step 1: Create a User Group](#step-1-create-a-user-group)
      * [Step 2: Create a User](#step-2-create-a-user)
  * [Test the Setup](#test-the-setup)
    * [Test Password Grant Flow](#test-password-grant-flow)
    * [Test Client Credentials Flow](#test-client-credentials-flow)
    * [Using the Access Tokens](#using-the-access-tokens)
<!-- TOC -->

In this setup, we will use a single domain: auth.example.com. All application components will be accessible via subpaths of this domain:

 - auth.example.com/users
 - auth.example.com/clients
 - auth.example.com/2fa
 - auth.example.com (for the core component)

All files will be placed under the main working directory:

```bash
/var/www/auth.example.com
```

## Prerequisites
Before starting the installation, ensure the following software is installed and properly configured on your system:

**PHP ≥ 8.1**

Required for running the application components.

**Apache HTTP Server**

Used to serve the application via auth.example.com and its subpaths.

**PostgreSQL**

Acts as the primary database for all modules.

**Composer**

Dependency manager for PHP, required to install project dependencies.

### The following PHP extensions are also required:

 - ext-ctype
 - ext-curl
 - ext-iconv
 - ext-openssl
 - ext-pgsql
 - ext-dom
 - ext-xml
 - ext-intl

## Database Setup (PostgreSQL)

In this section, we’ll set up the required PostgreSQL databases for the identity 
system. Rather than using the default admin (postgres) user, we’ll create a 
dedicated application user with access only to the relevant databases.

**Databases to Create**

 - identity-link
 - identity-link-db-users
 - identity-link-db-clients
 - identity-link-2fa

All will be owned by a dedicated user: iuser with password ipass.

**Important:** The iuser / ipass combination is used here for demonstration purposes only.
In production, you must use a strong, unique username and password.


1. Switch to the PostgreSQL administrative user

   ```bash
   sudo -i -u postgres
   ```

2. Enter the PostgreSQL interactive shell:

   ```bash
   psql
   ```

   You should now see a prompt like:

   ```bash
   postgres=#
   ```

3. Create a dedicated database user

   ```sql
   CREATE USER iuser WITH PASSWORD 'ipass';
   ```

4. Create the required databases and assign ownership

   ```sql
   CREATE DATABASE "identity-link" OWNER iuser;
   CREATE DATABASE "identity-link-db-users" OWNER iuser;
   CREATE DATABASE "identity-link-db-clients" OWNER iuser;
   CREATE DATABASE "identity-link-2fa" OWNER iuser;
   ```

5. Exit the PostgreSQL shell and exit back to your regular user:

   ```bash
   exit
   exit
   ```

##  Create Project Directories

**Note:** This guide assumes you are using a Debian-based distribution. The default web root is typically /var/www. Therefore, 
we will execute the following commands from that directory to create our virtual host's main project structure.

Navigate to the web root directory:

```bash
cd /var/www
```

Now, create the necessary subdirectories for each component of the virtual host:

```bash
sudo -u www-data mkdir -p auth.example.com/core/public \
                          auth.example.com/users/public \
                          auth.example.com/clients/public \
                          auth.example.com/2fa/public
```

This structure sets up the foundational directories for the authentication system components under 
the auth.example.com virtual host.

## Apache Virtual Host Configuration

To serve all components under the same domain (auth.example.com) with clean HTTPS access, configure Apache with the 
following virtual host settings:

### HTTPS + HTTP Redirect Setup
This configuration will:

 - Serve the core app at the root: https://auth.example.com/

 - Serve users, clients, and 2fa modules under their respective paths:

   - https://auth.example.com/users
   - https://auth.example.com/clients
   - https://auth.example.com/2fa

 - Redirect all HTTP requests (port 80) to HTTPS (port 443)

**Apache Config**

Save the following content into a file such as:

```bash
/etc/apache2/sites-available/auth.example.com.conf
```

```apacheconf
<VirtualHost *:80>
    ServerName auth.example.com

    # Redirect all HTTP traffic to HTTPS
    Redirect permanent / https://auth.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName auth.example.com

    # SSL Configuration
    SSLEngine on
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite "ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256"
    SSLHonorCipherOrder on
    SSLCompression off

    SSLCertificateChainFile /etc/ssl/letsencrypt/example.com/fullchain.crt
    SSLCertificateFile      /etc/ssl/letsencrypt/example.com/example.com.crt
    SSLCertificateKeyFile   /etc/ssl/letsencrypt/example.com/example.com.key

    # Default app (core)
    DocumentRoot /var/www/auth.example.com/core/public
    <Directory /var/www/auth.example.com/core/public>
       Options FollowSymLinks
       AllowOverride None
       Require all granted
   
       <IfModule mod_rewrite.c>
           RewriteEngine On
           RewriteBase /
           RewriteCond %{REQUEST_FILENAME} !-f
           RewriteCond %{REQUEST_FILENAME} !-d
           RewriteRule ^ index.php [QSA,L]
       </IfModule>
    </Directory>

    # Users module
    Alias /users /var/www/auth.example.com/users/public
    <Directory /var/www/auth.example.com/users/public>
       Options FollowSymLinks
       AllowOverride None
       Require all granted
   
       <IfModule mod_rewrite.c>
           RewriteEngine On
           RewriteBase /users/
           RewriteCond %{REQUEST_FILENAME} !-f
           RewriteCond %{REQUEST_FILENAME} !-d
           RewriteRule ^ index.php [QSA,L]
       </IfModule>
    </Directory>

    # Clients module
    Alias /clients /var/www/auth.example.com/clients/public
    <Directory /var/www/auth.example.com/clients/public>
       Options FollowSymLinks
       AllowOverride None
       Require all granted
   
       <IfModule mod_rewrite.c>
           RewriteEngine On
           RewriteBase /clients/
           RewriteCond %{REQUEST_FILENAME} !-f
           RewriteCond %{REQUEST_FILENAME} !-d
           RewriteRule ^ index.php [QSA,L]
       </IfModule>
    </Directory>

    # 2FA module
    Alias /2fa /var/www/auth.example.com/2fa/public
    <Directory /var/www/auth.example.com/2fa/public>
       Options FollowSymLinks
       AllowOverride None
       Require all granted
   
       <IfModule mod_rewrite.c>
           RewriteEngine On
           RewriteBase /2fa/
           RewriteCond %{REQUEST_FILENAME} !-f
           RewriteCond %{REQUEST_FILENAME} !-d
           RewriteRule ^ index.php [QSA,L]
       </IfModule>
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/auth-error.log
    CustomLog ${APACHE_LOG_DIR}/auth-access.log combined
</VirtualHost>
```

### Enable Required Apache Modules

Apache needs the following modules enabled:

```bash
sudo a2enmod ssl
sudo a2enmod rewrite
sudo a2enmod alias
```

### Enable the Virtual Host and Reload Apache

After saving your virtual host configuration file, activate it:

```bash
sudo a2ensite auth.example.com.conf
```

Check the configuration for syntax errors:

```bash
sudo apache2ctl configtest
```

Then reload Apache to apply changes:

```bash
sudo systemctl reload apache2
```

## Setup _users_ service 

The users service is responsible for user management. It stores all registered 
users along with their hashed passwords. The core service communicates with 
this one to retrieve user information during authentication and authorization 
processes.

### Clone the Repository

Start by removing the existing placeholder users directory (if it exists), 
and then clone the actual service code:

```bash
cd /var/www/auth.example.com
sudo rm -rf users
sudo -u www-data git clone https://github.com/sgoranov/identity-link-db-users.git users
```

### Move Into the Service Directory

From this point onward, all commands are executed from inside the users service directory:

```bash
cd /var/www/auth.example.com/users
```

### Install PHP Dependencies

Use Composer (as the www-data user) to install the project’s dependencies:

```bash
sudo -u www-data composer install
```

### Configure the Service

Create a local override for environment variables:

```bash
sudo -u www-data touch .env.local
```

Then open it in a text editor (e.g. nano, or use echo and redirection if scripting):

```bash
sudo -u www-data nano .env.local
```

Add the following content:

```txt
DB_USER=iuser
DB_PASSWORD=ipass
DB_HOST=localhost
DB_PORT=5432
APP_ENV=prod
JWT_JWKS_URI=https://auth.example.com/jwks

# Disable reset password functionality
# (requires working SMTP server)
FEATURE_RESET_PASSWORD_ENABLED=false

# Disable strong password validation rules
FEATURE_PASSWORD_VALIDATION_ENABLED=false
```

### Run Database Migrations

Now that the database is configured in .env.local, you can run the database 
migrations to create the required schema.
Execute the following command as the www-data user:

```bash
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

### Test the Service

To verify that the service is properly configured and running in production mode, 
you can test the built-in ping endpoint.
Open the following URL in your browser or use curl:

```bash
https://auth.example.com/users/api/v1/ping
```

If everything is working correctly, you should see the _pong_ response.


## Setup _clients_ service

The clients service is responsible for managing OAuth2 clients - these are the 
applications that will request tokens from the identity system.

It stores all registered clients in the database, along with their associated 
secrets, redirect URIs, grant types, and other relevant metadata. This service 
exposes a REST API that allows authorized users or systems to create, update, 
and delete client applications.

The core service interacts with the clients service during the token issuance 
process to validate client credentials and grant type permissions.

### Clone the Repository

Start by removing the existing placeholder clients directory (if it exists),
and then clone the actual service code:

```bash
cd /var/www/auth.example.com
sudo rm -rf clients
sudo -u www-data git clone https://github.com/sgoranov/identity-link-db-clients.git clients
```

### Move Into the Service Directory

From this point onward, all commands are executed from inside the clients service directory:

```bash
cd /var/www/auth.example.com/clients
```

### Install PHP Dependencies

Use Composer (as the www-data user) to install the project’s dependencies:

```bash
sudo -u www-data composer install
```

### Configure the Service

Create a local override for environment variables:

```bash
sudo -u www-data touch .env.local
```

Then open it in a text editor (e.g. nano, or use echo and redirection if scripting):

```bash
sudo -u www-data nano .env.local
```

Add the following content:

```txt
DB_USER=iuser
DB_PASSWORD=ipass
DB_HOST=localhost
DB_PORT=5432
APP_ENV=prod
JWT_JWKS_URI=https://auth.example.com/jwks
```

### Run Database Migrations

Now that the database is configured in .env.local, you can run the database
migrations to create the required schema.
Execute the following command as the www-data user:

```bash
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

### Test the Service

To verify that the service is properly configured and running in production mode,
you can test the built-in ping endpoint.
Open the following URL in your browser or use curl:

```bash
https://auth.example.com/clients/api/v1/ping
```

If everything is working correctly, you should see the _pong_ response.


## Setup _2FA_ service

The 2FA (Two-Factor Authentication) service provides an additional layer of 
security by implementing time-based one-time passwords (TOTP). This method is 
compatible with apps like Google Authenticator or Authy, where a secret key 
is generated and synchronized with the user’s device to produce temporary codes.

This service manages the creation, verification, and storage of 2FA 
secrets per user, enabling enhanced authentication flows. The core service 
communicates with the 2FA service to enforce two-factor checks during login.

### Clone the Repository

Start by removing the existing placeholder users directory (if it exists),
and then clone the actual service code:

```bash
cd /var/www/auth.example.com
sudo rm -rf 2fa
sudo -u www-data git clone https://github.com/sgoranov/identity-link-2fa.git 2fa
```

### Move Into the Service Directory

From this point onward, all commands are executed from inside the 2fa service directory:

```bash
cd /var/www/auth.example.com/2fa
```

### Install PHP Dependencies

Use Composer (as the www-data user) to install the project’s dependencies:

```bash
sudo -u www-data composer install
```

### Configure the Service

Create a local override for environment variables:

```bash
sudo -u www-data touch .env.local
```

Then open it in a text editor (e.g. nano, or use echo and redirection if scripting):

```bash
sudo -u www-data nano .env.local
```

Add the following content:

```txt
DB_USER=iuser
DB_PASSWORD=ipass
DB_HOST=localhost
DB_PORT=5432
APP_ENV=prod
JWT_JWKS_URI=https://auth.example.com/jwks

# URL to redirect to after successful 2FA verification
TWO_FA_REDIRECT_URI=https://auth.example.com/login/2fa
```

### Run Database Migrations

Now that the database is configured in .env.local, you can run the database
migrations to create the required schema.
Execute the following command as the www-data user:

```bash
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

### Test the Service

To verify that the service is properly configured and running in production mode,
you can test the built-in ping endpoint.
Open the following URL in your browser or use curl:

```bash
https://auth.example.com/2fa/api/v1/ping
```

If everything is working correctly, you should see the _pong_ response.

## Setup _core_ service

The core service acts as the central hub of the identity system. It coordinates 
interactions between the other services - users, clients, and 2FA - to manage 
authentication and authorization workflows.

### Clone the Repository

Start by removing the existing placeholder core directory (if it exists),
and then clone the actual service code:

```bash
cd /var/www/auth.example.com
sudo rm -rf core
sudo -u www-data git clone https://github.com/sgoranov/identity-link.git core
```

### Move Into the Service Directory

From this point onward, all commands are executed from inside the core service directory:

```bash
cd /var/www/auth.example.com/core
```

### Install PHP Dependencies

Use Composer (as the www-data user) to install the project’s dependencies:

```bash
sudo -u www-data composer install
```

### Configure the Service

Create a local override for environment variables:

```bash
sudo -u www-data touch .env.local
```

Then open it in a text editor (e.g. nano, or use echo and redirection if scripting):

```bash
sudo -u www-data nano .env.local
```

Add the following content:

```txt
DB_USER=iuser
DB_PASSWORD=ipass
DB_HOST=localhost
DB_PORT=5432
APP_ENV=prod

USER_QUERY_ENDPOINT=https://auth.example.com/users/api/v1/query
USER_AUTH_ENDPOINT=https://auth.example.com/users/api/v1/auth
USER_FETCH_ENDPOINT=https://auth.example.com/users/api/v1/user/{id}

CLIENT_AUTH_ENDPOINT=https://auth.example.com/clients/api/v1/auth
CLIENT_QUERY_ENDPOINT=https://auth.example.com/clients/api/v1/query

TWO_FA_AUTH_ENDPOINT=https://auth.example.com/2fa/api/v1/auth
TWO_FA_INDEX_ENDPOINT=https://auth.example.com/2fa/2fa/verify/{id}

# Leave empty to disable the reset password functionality
RESET_PASSWORD_URL=
```

### Run Database Migrations

Now that the database is configured in .env.local, you can run the database
migrations to create the required schema.
Execute the following command as the www-data user:

```bash
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

### Generate Keys

The core service requires a set of cryptographic keys to issue and manage secure OAuth2 tokens.

```bash
sudo -u www-data php bin/console identity-link:generate-keys
sudo chmod 600 var/private.key
```

This will create the following files in the var/ directory:

 - private.key – Used to sign access and refresh tokens
 - public.key – Used to verify tokens issued by the server
 - encryption.key – Used to encrypt and decrypt sensitive token data, such as authorization codes

### Test the Service

To verify that the core service is properly configured and running in production mode, you can test the OAuth2 
authorization endpoint by opening the following URL in your browser:

```bash
https://auth.example.com/oauth2/auth?response_type=code&client_id=client&redirect_uri=http://example.com&state=mystate&scope=openid
```

If the authorization page loads correctly, and you see the expected login or 
consent screen, this confirms that the core service is functioning as intended.

## Generate Data

We can use the REST APIs provided by the services to create a client and a user.
To access these APIs, we need a valid JWT token. Generate one by running:

```bash
cd /var/www/auth.example.com/core/
sudo -u www-data php bin/console identity-link:generate-jwt > /tmp/token
```

### Generate Client Data

#### Step 1: Create a Client Group

Use the following curl command to create a new group for your client:

```bash
curl --silent --show-error --location "https://auth.example.com/clients/api/v1/group" \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer $(cat /tmp/token)" \
  --data '{"name": "administrator"}' \
  --write-out "\nHTTP Status: %{http_code}\n"
```

This will create a group named administrator and return a UUID in the response.
Save this UUID — you'll need it in the next step.


#### Step 2: Create the Client

Replace XXX with the UUID of the group created in Step 1:

```bash
curl --silent --show-error --location "https://auth.example.com/clients/api/v1/client" \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer $(cat /tmp/token)" \
  --data '{
    "name": "client",
    "description": "description",
    "redirectUri": "https://example.com",
    "grantTypes": [
      "client_credentials",
      "authorization_code",
      "password",
      "refresh_token"
    ],
    "groups": ["XXX"],
    "isPublic": false
  }' \
  --write-out "\nHTTP Status: %{http_code}\n"
```

This command registers a new client and associates it with the specified group.
The response will contain the client UUID, which you'll use in the next step.

#### Step 3: Create the Client Secret


Replace XXX with the client UUID from the previous step.
This command will generate a secret that expires one month from today:

```bash
curl --silent --show-error --location "https://auth.example.com/clients/api/v1/secret" \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer $(cat /tmp/token)" \
  --data "$(jq -n --arg exp "$(date -d '+1 month' --iso-8601=seconds)" --arg client_id "XXX" '{
    password: "client",
    passwordHint: "pass hint",
    expirationDateTime: $exp,
    client: $client_id
  }')" \
  --write-out "\nHTTP Status: %{http_code}\n"
```

**Note**: You must have jq installed (sudo apt install jq) for this to work.

### Generate User Data

#### Step 1: Create a User Group

To begin, create a group that your user will belong to:

```bash
curl --silent --show-error --location "https://auth.example.com/users/api/v1/group" \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer $(cat /tmp/token)" \
  --data '{"name": "administrator"}' \
  --write-out "\nHTTP Status: %{http_code}\n"
```

This will create a group named administrator and return a UUID in the response.
Save this UUID — you'll need it in the next step.

#### Step 2: Create a User

Now create the user, replacing XXX with the UUID of the group created above:

```bash
curl --silent --show-error --location "https://auth.example.com/users/api/v1/user" \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer $(cat /tmp/token)" \
  --data '{
    "username": "user",
    "password": "pass",
    "firstName": "Firstname",
    "lastName": "Lastname",
    "email": "test@example.com",
    "grantTypes": [
      "password",
      "authorization_code",
      "refresh_token"
    ],
    "groups": ["XXX"]
  }' \
  --write-out "\nHTTP Status: %{http_code}\n"
```

This command creates a new user, assigns it to the given group, and enables it for the specified grant types.

## Test the Setup

After completing the configuration and data setup, you can verify that the system 
is working correctly by testing the OAuth2 flows.

### Test Password Grant Flow

This test simulates a user logging in with a username and password.

```bash
curl --silent --show-error --insecure \
  --user client:client \
  --location "https://auth.example.com/oauth2/token" \
  --data "grant_type=password&username=user&password=pass&scope=openid" \
  --write-out "\nHTTP Status: %{http_code}\n"
```

If successful, you will receive a response containing both an access token and a refresh token.

### Test Client Credentials Flow

This test simulates a machine-to-machine authentication using the client’s credentials.

```bash
curl --silent --show-error --insecure \
  --user client:client \
  --location "https://auth.example.com/oauth2/token" \
  --data "grant_type=client_credentials&scope=openid" \
  --write-out "\nHTTP Status: %{http_code}\n"
```

If successful, the response will contain an access token.


### Using the Access Tokens

The access tokens obtained from either flow can be used to authenticate 
requests to the system's REST APIs. With a valid token, you can:

 - Create or manage users
 - Create or manage clients
 - Assign or update secrets
 - Organize and manage groups
