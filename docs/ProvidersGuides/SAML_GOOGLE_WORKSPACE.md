# SAML SSO Setup Guide: Google Workspace

Configuring SAML authentication using Google Workspace as the Identity Provider (IdP) for project. Covers Google Admin Console setup, local tunneling with Ngrok, and the project's environment variables.

## Table of Contents

- [1. Google Admin Console Setup](#1-google-admin-console-setup)
- [2. Ngrok Setup (HTTPS Tunnel for Localhost)](#2-ngrok-setup-https-tunnel-for-localhost)
- [3. Project Configuration (.env)](#3-project-configuration-env)
- [Notes & Known Issues](#notes--known-issues)

---

## 1. Google Admin Console Setup

First step: register the application in the Google Workspace Admin Console to generate the SAML credentials.

1. In the Admin Console's left-hand menu, go to **Apps → Web and mobile apps**.
2. Click **Add app** → **Add custom SAML app**.
3. Fill in the application details (name, logo, etc.).
4. In the Google Identity Provider details section, select **Option 2** and copy:
    - **SSO URL**
    - **Entity ID**
    - **X.509 Certificate**

   These values will be used in the project's `.env` file (see [section 3](#3-project-configuration-env)).

---

## 2. Ngrok Setup (HTTPS Tunnel for Localhost)

Google Workspace strictly requires response URLs (ACS — Assertion Consumer Service) to use HTTPS. For local development, we use **Ngrok** to expose the local app over a secure address.

### Installation

Full instructions at [ngrok.com/download/linux](https://ngrok.com/download/linux). Via `apt`:

```bash
curl -sSL https://ngrok-agent.s3.amazonaws.com/ngrok.asc \
  | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null \
  && echo "deb https://ngrok-agent.s3.amazonaws.com bookworm main" \
  | sudo tee /etc/apt/sources.list.d/ngrok.list \
  && sudo apt update \
  && sudo apt install ngrok
```

### Authentication

Requires an account on ngrok.com to obtain the authtoken from the dashboard:

```bash
ngrok config add-authtoken "<YOUR_AUTHTOKEN>"
```

### Start the tunnel

```bash
ngrok http 80
```

The terminal will output a **Forwarding URL**, for example:

```
https://zigzagged-shabby-data.ngrok-free.dev
```

> This domain must be used for both the **Entity ID / ACS URL** in Google Admin and in the project's `.env` file.

---

## 3. Project Configuration (`.env`)

With the Google Admin and Ngrok values collected, update the environment's `.env` file (e.g., the OpenRoaming portal):

```env
#######################################
# SAML CONFIGURATION
#######################################
SAML_IDP_ENTITY_ID=https://accounts.google.com/o/saml2?idpid=C03gnaajg
SAML_IDP_SSO_URL=https://accounts.google.com/o/saml2/idp?idpid=C03gnaajg
SAML_IDP_X509_CERT="MIIDdDCCAlygAwIBAgIGAZ8S1lI+MA0GCSqGSIb3DQEBCwUAMHsxFDASBgNVBAoTC0dvb2dsZSBJbmMu..."

# Ngrok Forwarding URLs
SAML_SP_ENTITY_ID=https://zigzagged-shabby-data.ngrok-free.dev/saml/metadata
SAML_SP_ACS_URL=https://zigzagged-shabby-data.ngrok-free.dev/saml/acs
SAML_DASHBOARD_ACS_URL=https://zigzagged-shabby-data.ngrok-free.dev/dashboard/saml/acs

# Attribute Mapping
SAML_IDENTIFIER_ATTRIBUTE=email
SAML_ATTRIBUTE_MAPPING='{"uuid":"email","email":"email","first_name":"givenName","last_name":"surname","username":"email"}'
```

> **Note:** attribute names in the project's `.env` must exactly match the attribute mapping configured in Google Workspace.

---

## Notes & Known Issues

- The Ngrok URL changes on every new tunnel (on the free tier), so both Google Admin and `.env` must be updated whenever the tunnel restarts.
- Bundle used in this project: `nbgrp/onelogin-saml-bundle`.
- Currently open blockers:
    - Enabling user access in Google Admin for the test app.
    - Initiating the login flow from the Ngrok URL.
