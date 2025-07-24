<?php

namespace App\Traits;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait GlobalStatus
{

    public static function changeStatus($id, $column = 'status')
    {
        $modelName = get_class();
        $query     = $modelName::findOrFail($id);
        if ($query->$column == Status::ENABLE) {
            $query->$column = Status::DISABLE;
        } else {
            $query->$column = Status::ENABLE;
        }
        $message       = keyToTitle($column). ' changed successfully';

        $query->save();
        $notification = trans($message);
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return back()->with($notification);
    }

    public function statusBadge(): Attribute
    {
        return new Attribute(
            get: fn () => $this->badgeData(),
        );
    }

    public function badgeData()
    {
        $html = '';
        if ($this->status == Status::ENABLE) {
            $html = '<span class="badge bg-success">' . trans('translate.Enabled') . '</span>';
        } else {
            $html = '<span class="badge bg-danger">' . trans('translate.Disabled') . '</span>';
        }
        return $html;
    }

    public function scopeActive($query)
    {
        return $query->where('status', Status::ENABLE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', Status::DISABLE);
    }
}
