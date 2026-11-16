<?php

namespace App\Models;

use App\Enums\LibraryRole;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pivot model za many-to-many vezu korisnik <-> biblioteka.
 *
 * Laravelov `Pivot::delete()` za pivot sa kompozitnim ključem radi hard
 * delete, pa se ovde `delete()` preusmerava na `runSoftDelete()` (soft
 * delete), osim kod `forceDelete()` (kada je `$this->forceDeleting`).
 */
class LibraryUserPivot extends Pivot
{
    use SoftDeletes;

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => LibraryRole::class,
            'deleted_at' => 'datetime',
        ];
    }

    public function delete()
    {
        $this->mergeAttributesFromClassCasts();

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $this->touchOwners();

        if ($this->forceDeleting) {
            $this->setKeysForSaveQuery($this->newModelQuery())->forceDelete();
            $this->exists = false;
        } else {
            $this->runSoftDelete();
        }

        $this->fireModelEvent('deleted', false);

        return true;
    }
}
