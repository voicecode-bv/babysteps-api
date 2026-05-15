<?php

namespace App\Models;

use Database\Factories\WaitingListEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaitingListEntry extends Model
{
    /** @use HasFactory<WaitingListEntryFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['email'];
}
