<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $country = $_POST['country'];
    $state = $_POST['state'];
    $locality = $_POST['locality'];
    $organization = $_POST['organization'];
    $orgUnit = $_POST['orgUnit'];
    $commonName = $_POST['commonName'];
    $email = $_POST['email'];

    $dn = array(
        "countryName" => $country,
        "stateOrProvinceName" => $state,
        "localityName" => $locality,
        "organizationName" => $organization,
        "organizationalUnitName" => $orgUnit,
        "commonName" => $commonName,
        "emailAddress" => $email
    );

    // Generate a new private (and public) key pair
    $privkey = openssl_pkey_new(array(
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEY_RSA,
    ));

    // Generate a certificate signing request
    $csr = openssl_csr_new($dn, $privkey);

    // You can use your CA certificate and private key to sign the CSR here.
    // For demonstration purposes, we will self-sign it.
    $sscert = openssl_csr_sign($csr, null, $privkey, 365);

    // Export the certificate and private key to files
    openssl_x509_export_to_file($sscert, 'server.crt');
    openssl_pkey_export_to_file($privkey, 'server.key');
    openssl_csr_export_to_file($csr, 'server.csr');

    // Create a CA cert
    $caKey = openssl_pkey_new(array("private_key_bits" => 2048, "private_key_type" => OPENSSL_KEY_RSA));
    $caCert = openssl_csr_sign($csr, null, $caKey, 365);
    openssl_x509_export_to_file($caCert, 'ca.crt');
    openssl_pkey_export_to_file($caKey, 'ca.key');

    // Create sub CA
    $subCaKey = openssl_pkey_new(array("private_key_bits" => 2048, "private_key_type" => OPENSSL_KEY_RSA));
    $subCaCert = openssl_csr_sign($csr, $caCert, $subCaKey, 365);
    openssl_x509_export_to_file($subCaCert, 'sub-ca.crt');
    openssl_pkey_export_to_file($subCaKey, 'sub-ca.key');

    // Create chained cert
    $chainedCert = $sscert . $subCaCert . $caCert;
    file_put_contents('chained.crt', $chainedCert);

    // Create an instruction file
    $instructions = "1. Place 'ca.crt', 'server.crt', 'sub-ca.crt', 'server.key', and 'chained.crt' in the appropriate directories.\n";
    $instructions .= "2. Update your web server configuration to point to these files.\n";
    $instructions .= "3. Restart your web server.\n";
    file_put_contents('instruction.txt', $instructions);

    // Create a zip file
    $zip = new ZipArchive();
    if ($zip->open('certificates.zip', ZipArchive::CREATE) === TRUE) {
        $zip->addFile('ca.crt');
        $zip->addFile('server.crt');
        $zip->addFile('sub-ca.crt');
        $zip->addFile('server.key');
        $zip->addFile('chained.crt');
        $zip->addFile('instruction.txt');
        $zip->close();
    }

    // Redirect to download page
    header("Location: http://download.ewubdca.com/index.html");
}
?>
