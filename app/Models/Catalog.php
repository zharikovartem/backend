<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Catalog
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $name
 * @property int $is_active
 * @property int|null $total_count
 * @property int $descriptions_count
 * @property int $parent_id
 * @property string $label
 * @property string|null $params
 * @property string|null $labels
 * @property string|null $type
 * @property string|null $url
 * @property string|null $full_url
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog query()
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereDescriptionsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereFullUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereLabels($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereParams($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereTotalCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Catalog whereUrl($value)
 * @mixin \Eloquent
 */
class Catalog extends Model
{
    protected $table = 'Catalog';

    protected $fillable = [
        'id',
        'name',
        'is_active',
        'total_count',
        'type',
        'parent_id',
        'password',
        'created_at',
        'updated_at',
        'params',
        'label',
        'labels',
        'url',
        'full_url'
    ];
}
