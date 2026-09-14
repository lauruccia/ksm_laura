<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un ruolo e' un elenco di permessi con un nome.
     *
     * I permessi stanno in una colonna JSON, come le voci dei piani:
     * l'elenco valido lo decide `App\Support\Permissions`, non il database.
     * `is_system` protegge il ruolo che non deve poter sparire.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // Il ruolo di sistema nasce qui e non nel seeder: senza di lui
        // la migrazione successiva non avrebbe a chi assegnare gli
        // amministratori gia' presenti, e resterebbero chiusi fuori.
        $now = now();

        DB::table('roles')->insert([
            [
                'name' => 'Amministratore',
                'slug' => 'super-admin',
                'description' => 'Accesso completo a tutto il pannello. Non si puo eliminare.',
                'permissions' => json_encode(Permissions::keys()),
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Redazione',
                'slug' => 'redazione',
                'description' => 'Pagine, banner e domini.',
                'permissions' => json_encode([
                    Permissions::DASHBOARD_VIEW,
                    Permissions::CONTENT_MANAGE,
                ]),
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Assistenza clienti',
                'slug' => 'assistenza',
                'description' => 'Ordini, pagamenti e aziende in sola lettura.',
                'permissions' => json_encode([
                    Permissions::DASHBOARD_VIEW,
                    Permissions::COMPANIES_VIEW,
                    Permissions::SUBSCRIPTIONS_VIEW,
                    Permissions::CATALOG_VIEW,
                    Permissions::ORDERS_VIEW,
                    Permissions::ORDERS_MANAGE,
                    Permissions::PAYMENTS_VIEW,
                    Permissions::USERS_VIEW,
                ]),
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
