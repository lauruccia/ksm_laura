<?php

namespace App\Models;

use App\Support\DomainNameList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un nome a dominio del portafoglio KSM, elencato nella pagina pubblica.
 *
 * Il nome si salva senza "www." e in minuscolo, l'estensione si ricava da solo.
 */
class DomainName extends Model
{
    /** Ordinamenti della pagina pubblica: valore del filtro => etichetta. */
    public const SORTS = [
        'nome' => 'Nome (A-Z)',
        'nome_desc' => 'Nome (Z-A)',
        'estensione' => 'Estensione',
        'corti' => 'Più corti prima',
        'recenti' => 'Aggiunti di recente',
    ];

    protected $fillable = ['name', 'extension', 'category', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function setNameAttribute(?string $value): void
    {
        $host = DomainNameList::normalize((string) $value) ?? strtolower(trim((string) $value));

        $this->attributes['name'] = $host;
        $this->attributes['extension'] = DomainNameList::extension($host);
    }

    /** L'indirizzo come lo digita chi arriva: www.pub.it */
    public function getDisplayAttribute(): string
    {
        return 'www.'.$this->name;
    }

    public function getUrlAttribute(): string
    {
        return 'http://www.'.$this->name;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Contiene il testo cercato, senza che % e _ facciano da jolly. */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], strtolower(trim(preg_replace('/^(https?:\/\/)?(www\.)?/i', '', $term))));

        return $query->where('name', 'like', "%$escaped%");
    }

    /** Inizia per la lettera data, oppure "0" per i nomi che iniziano con una cifra. */
    public function scopeStartingWith(Builder $query, string $letter): Builder
    {
        return $letter === '0'
            ? $query->where('name', '>=', '0')->where('name', '<', 'a')
            : $query->where('name', 'like', $letter.'%');
    }

    public function scopeSortedBy(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'nome_desc' => $query->orderByDesc('name'),
            'estensione' => $query->orderBy('extension')->orderBy('name'),
            'corti' => $query->orderByRaw('LENGTH(name)')->orderBy('name'),
            'recenti' => $query->orderByDesc('id'),
            default => $query->orderBy('name'),
        };
    }
}
