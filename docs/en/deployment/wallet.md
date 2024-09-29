# Digital Wallet
## Generating a Google Wallet Key:

To generate a Google Wallet key, follow these steps via the Google Cloud Console:

1. Go to [Google Cloud Console](https://console.cloud.google.com/apis/credentials) and create a new key:
    - **Create credentials** -> **Service Account** -> **Create**
2. Create a new private key file:
    - **Edit key** (pencil icon) -> **Keys** -> **Add Key** -> **JSON**
3. Delete old or leaked keys:
    - **Edit key** (pencil icon) -> **Keys** -> **Delete** (trash icon)

Save the JSON file as `google.json` in your `keys` folder.

---

## Certificate for Apple Wallet:

To generate an Apple Wallet key using a CSR file (must end with `.certSigningRequest`), run the following command:

```bash
openssl req -new -newkey 2048 -nodes -keyout FoodsharingAppleWallet.key -out csrFoodsharingAppleWallet.certSigningRequest
```

Use the following details when prompted:

- **Country Name (2 letter code) [AU]:** DE
- **State or Province Name (full name) [Some-State]:** Germany
- **Locality Name (eg, city) []:** Köln
- **Organization Name (eg, company) [Internet Widgits Pty Ltd]:** foodsharing e.V.
- **Organizational Unit Name (eg, section) []:** foodsharing.de
- **Common Name (e.g. server FQDN or YOUR name) []:** foodsharing.de
- **Email Address []:** (apple account email)

For the additional attributes, leave them empty:

- **Challenge password []:**
- **Optional company name []:**

After that, upload the CSR file to the Apple website and you'll receive a `pass.cer` file.

---

### Steps on Apple Website:

- Go to [Apple Developer](https://developer.apple.com/account/resources/certificates/list)

- **Pass Type ID Certificate** -> **Continue**
- Enter your Pass certificate Name: `Foodsharing Production`
- Pass Type ID: `pass.de.foodsharing.ausweis` -> **Continue**
- Upload the certificate -> **Continue**
- Download the certificate

---

### Generating the PEM and P12 Files:

1. Convert the `pass.cer` file to `pass.pem` using the following command:

    ```bash
    openssl x509 -in pass.cer -inform DER -out pass.pem -outform PEM
    ```

2. Then, use the `pass.pem` and the private key to generate the `apple.p12` certificate:

    ```bash
    openssl pkcs12 -export -out apple.p12 -inkey FoodsharingAppleWallet.key -in pass.pem
    ```

Save the `apple.p12` file in your `keys` folder.

**Important:** The Apple key is only valid for 1 year, so you'll need to create a new one after it expires!
