<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\ManagementApiToken;
use Illuminate\Console\Command;

class IssueManagementApiToken extends Command
{
    protected $signature = 'management:issue-token
        {company : ID numerico o UUID pubblico dell azienda}
        {name : Nome descrittivo del collegamento}
        {--scope=* : Scope; ripetere l opzione per aggiungerne altri}
        {--days=90 : Giorni di validita; 0 significa nessuna scadenza}';

    protected $description = 'Genera un token Portal Connector API per una singola azienda';

    public function handle(): int
    {
        $identifier = (string) $this->argument('company');
        $company = Company::query()
            ->where(fn ($query) => $query->where('public_id', $identifier)
                ->when(ctype_digit($identifier), fn ($query) => $query->orWhere('id', (int) $identifier)))
            ->first();

        if (! $company) {
            $this->error('Azienda non trovata.');

            return self::FAILURE;
        }

        $scopes = $this->option('scope') ?: ManagementApiToken::READ_SCOPES;
        $invalid = array_diff($scopes, ManagementApiToken::SCOPES);

        if ($invalid !== []) {
            $this->error('Scope non supportati: '.implode(', ', $invalid));

            return self::FAILURE;
        }

        $days = max(0, (int) $this->option('days'));
        [$token, $raw] = ManagementApiToken::issue($company, (string) $this->argument('name'), $scopes, $days === 0 ? null : $days);

        $this->warn('Copia ora il token: non sara mostrato di nuovo.');
        $this->line($raw);
        $this->newLine();
        $this->table(['Azienda', 'Prefisso', 'Scope', 'Scadenza'], [[
            $company->name,
            $token->token_prefix,
            implode(', ', $token->scopes),
            $token->expires_at?->toIso8601String() ?? 'nessuna',
        ]]);

        return self::SUCCESS;
    }
}
