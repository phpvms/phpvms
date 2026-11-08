<?php

declare(strict_types=1);

namespace ProbeAddon\Models {
    use Illuminate\Database\Eloquent\Model;

    class ProbeThing extends Model
    {
        protected $table = 'probe_things';
    }

    class Unprefixed extends Model
    {
        protected $table = 'things';
    }
}

namespace OtherAddon\Models {
    use Illuminate\Database\Eloquent\Model;

    class Foreign extends Model
    {
        protected $table = 'probe_foreign';
    }
}
