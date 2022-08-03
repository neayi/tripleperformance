Import-Certificate -FilePath ".cache\ssl\root.crt" -CertStoreLocation Cert:\LocalMachine\Root
Import-Certificate -FilePath ".cache\ssl\server.crt" -CertStoreLocation Cert:\LocalMachine\Root
