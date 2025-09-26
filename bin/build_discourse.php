<?php

/**
 * Builds a discourse yml config from a template, using the environment variables.
 */

$discourse_yml_template = __DIR__ . '/discourse/forum.yml';

// Get the output filepath from the command line argument
if ($argc < 2) {
    echo "Usage: php build_discourse.php <output_file>\n";
    exit(1);
}  

$output_file = $argv[1];
if (!is_writable(dirname($output_file))) {
    echo "Error: Cannot write to the directory of $output_file\n";
    exit(1);
}

// Read the .env file if it exists
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $env = parse_ini_file($env_file);
}

// Get the current dev or prod environment from the ENV variable
if ($env['ENV'] !== 'dev' && $env['ENV'] !== 'prod') {
    echo "Error: ENV variable must be set to 'dev' or 'prod'\n";
    exit(1);
}

// If we are on the dev environment, get the docker-compose.yml file and extract the TRAFIK_1.7_HOSTS variable
if ($env['ENV'] === 'dev') {
    $hosts = ['forum.dev.tripleperformance.fr', 'forum.en.dev.tripleperformance.ag'];

} else { 
    $hosts = ['forum.tripleperformance.fr', 'en.forum.tripleperformance.ag'];
}

$replacements = [
    '@TRAFIK_1.7_HOSTS@' => 'Host:' . implode(',', $hosts),
    '@TRAFIK_3.57_HOSTS@' => 'Host(`' . implode('`) || Host(`', $hosts) . '`)',
    '@NETWORK_ALIASES@' => '--network-alias=' . implode(' --network-alias=', $hosts),
    '@DISCOURSE_HOSTNAME@' => $hosts[0],
    '@DISCOURSE_SMTP_ADDRESS@' => $env['N8N_SMTP_HOST'] ?? 'smtp.mailtrap.io',
    '@DISCOURSE_SMTP_PORT@' => $env['N8N_SMTP_PORT'] ?? '2525',
    '@DISCOURSE_SMTP_USER_NAME@' => $env['N8N_SMTP_USER'] ?? '6b76da7b18d1f5',
    '@DISCOURSE_SMTP_PASSWORD@' => $env['N8N_SMTP_PASS'] ?? 'e533db58674a64',
    '@DISCOURSE_MAXMIND_LICENSE_KEY@' => $env['MAXMIND_LICENSE_KEY'] ?? '30IYQsKfsamtnj30',
];

$replacements['@OTHER_LANGUAGES@'] = '';
foreach (['en'] as $k => $langCode) {
    $host = $env['ENV'] === 'dev' ? "forum.$langCode.dev.tripleperformance.ag" : "$langCode.forum.tripleperformance.ag";

    $k = $k + 2;
    $subSite = <<<EOT
        forum_{$langCode}:
           adapter: postgresql
           database: {$langCode}_discourse
           pool: 25
           timeout: 5000
           db_id: {$k}
           host_names:
             - {$host}
EOT;

    $replacements['@OTHER_LANGUAGES@'] .= $subSite . "\n";
}

print_r($replacements);


// Load the template file
$template = file_get_contents($discourse_yml_template);
if ($template === false) {
    echo "Error: Cannot read template file $discourse_yml_template\n";
    exit(1);
}

// Replace the placeholders with the environment variables
$output = str_replace(array_keys($replacements), array_values($replacements), $template);
if ($output === null) {
    echo "Error: Failed to replace placeholders in template\n";
    exit(1);
}

// Write the output to the specified file
$result = file_put_contents($output_file, $output);
if ($result === false) {
    echo "Error: Cannot write to output file $output_file\n";
    exit(1);
}

echo "Discourse config written to $output_file\n";

exit(0);
