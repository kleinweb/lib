<?php

// SPDX-FileCopyrightText: (C) 2025-2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Support\Environment;
use League\Uri\Components\Domain;
use League\Uri\Components\Path;
use League\Uri\Uri;
use Webmozart\Assert\Assert;
use WP_CLI;
use WP_Site;

final class DemapDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
    protected $signature = 'app:tenancy:demap-domains {--dry-run} {--subdomains} {--skip-replace}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert domain-mapped site URLs to unmapped site URLs';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $dryRun = $this->option('dry-run');
        $subdomains = $this->option('subdomains');
        $replace = ! $this->option('skip-replace');

        $dryRunPrefix = $dryRun ? '[DRY-RUN]' : '';
        $resultCols = ['blog_id', 'from_url', 'to_url'];

        $sites = \collect(get_sites());
        $primarySite = get_site(get_main_site_id());
        Assert::isInstanceOf($primarySite, WP_Site::class);

        $results = [];
        $followupCommands = [];
        foreach ($sites as $site) {
            $id = (int) $site->blog_id;
            $oldSiteUrl = get_site_url($id);
            $oldUri = Uri::new($oldSiteUrl);

            if ($site->domain === $primarySite->domain) {
                $this->warn("Skipping same-domain site {$id} :: <{$oldUri}>");
                continue;
            }

            $this->info("Processing site {$id} :: <{$oldUri}>...");

            $this->line("{$dryRunPrefix} Storing initial values in site meta...");

            if (! get_site_meta($id, 'orig_host')) {
                if (! $dryRun) {
                    add_site_meta($id, 'orig_host', $site->domain);
                }
                $this->line("{$dryRunPrefix} Saved site {$id} meta 'orig_host' as {$site->domain}");
            }

            if (! get_site_meta($id, 'orig_path')) {
                if (!$dryRun) {
                    add_site_meta($id, 'orig_path', $site->path);
                }
                $this->info("{$dryRunPrefix} Saved site {$id} meta 'orig_path' as {$site->path}");
            }

            if (! get_site_meta($id, 'orig_siteurl')) {
                if (!$dryRun) {
                    add_site_meta($id, 'orig_siteurl', $oldSiteUrl);
                }
                // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
                $this->line("{$dryRunPrefix} Saved site {$id} meta 'orig_siteurl' as {$oldSiteUrl}");
            }

            $oldHost = $oldUri->getHost();
            $oldDomain = Domain::new($oldHost)->withRootLabel();
            // Next level below top-level domain name.
            $label = $oldDomain->get(-1);

            $this->line("Inferred '{$label}' as site label...");

            $newUri = $subdomains
                ? $oldUri->withHost($label . '.' . $primarySite->domain)
                : $oldUri->withHost($primarySite->domain)
                      ->withPath(Path::new('/' . $label));

            $this->line("{$dryRunPrefix} Setting new URL for site {$id} to <{$newUri}>");

            switch_to_blog($id);

            // Rewrite rules can't be flushed during switch to blog.
            delete_option('rewrite_rules');

            $siteDetails = [
                'domain' => $newUri->getHost(),
                'path' => $newUri->getPath(),
            ];

            if (!$dryRun) {
                update_blog_details($id, $siteDetails);
                update_option('siteurl', $newUri->toString());
                update_option('home', $newUri->toString());
            }

            restore_current_blog();

            $result = [
                $id,
                $oldUri->toString(),
                $newUri->toString(),
            ];
            $results[] = array_combine($resultCols, $result);

            $this->line("{$dryRunPrefix} Changed site {$id} URL from <{$oldUri}> to <{$newUri}>");

            $replacementCommand = "search-replace {$oldUri} {$newUri}";
            if ($replace) {
                $this->line("{$dryRunPrefix} Performing database replacements for URL change...");

                WP_CLI::runcommand($replacementCommand, [
                    'return' => false,
                    // Reuse the current process for access to database connection.
                    'launch' => false,
                    'exit_error' => false,
                    'command_args' => [
                        '--network',
                        '--skip-columns=guid',
                        $dryRun ? '--dry-run' : '',
                    ],
                ]);
            } else {
                $wpCmd
                    = match (constant('WP_ENV')) {
                        Environment::LOCAL => 'ddev wp ',
                        default => 'wp @' . constant('WP_ENV'),
                    };
                // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
                $followupCommands[] = $wpCmd . $replacementCommand . ' --network --skip-columns=guid ' . ($dryRun ? '--dry-run' : '');
            }
        }


        $this->table($resultCols, $results);

        // WP_CLI::runcommand('site list --fields=blog_id,url,domain,path');        WP_CLI::runcommand('site list --fields=blog_id,url,domain,path');

        $this->info("{$dryRunPrefix} Done.");

        if ($replace) {
            return;
        }

        $this->line('');
        $this->info('Run the following commands to perform database replacements:');
        $this->line('');
        foreach ($followupCommands as $cmd) {
            $this->line($cmd);
        }
    }
}
