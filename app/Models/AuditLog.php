<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model {
    protected $fillable=['merchant_id','user_id','action','entity_type','entity_id','before','after','ip_address','user_agent'];
    protected function casts(): array { return ['before'=>'array','after'=>'array']; }
}
