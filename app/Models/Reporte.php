<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reporte extends Model
{
    use HasFactory;

    protected $table = 'reporte';
    protected $primaryKey = 'id_reporte';
    public $timestamps = false;

    const CREATED_AT = 'fecha_reporte';

    protected $fillable = [
        'id_usuario_reporta', 'tipo', 'id_publicacion', 'id_persona',
        'id_usuario_reportado', 'objetivo_titulo', 'motivo', 'descripcion',
        'estado_reporte', 'id_administrador', 'fecha_gestion',
        'id_escalado_por', 'nota_escalado', 'fecha_escalado',
    ];

    protected $casts = [
        'fecha_reporte' => 'datetime',
        'fecha_gestion' => 'datetime',
        'fecha_escalado' => 'datetime',
    ];

    public function autor()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_reporta', 'id_usuario');
    }

    public function publicacion()
    {
        return $this->belongsTo(Publicacion::class, 'id_publicacion', 'id_publicacion');
    }

    public function persona()
    {
        return $this->belongsTo(PersonaRoomie::class, 'id_persona', 'id_persona');
    }

    public function usuarioReportado()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_reportado', 'id_usuario');
    }

    /** Quien lo gestionó (resolvió o descartó): un administrador o un moderador. */
    public function administrador()
    {
        return $this->belongsTo(Usuario::class, 'id_administrador', 'id_usuario');
    }

    /** Moderador que lo escaló a un administrador. */
    public function escaladoPor()
    {
        return $this->belongsTo(Usuario::class, 'id_escalado_por', 'id_usuario');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado_reporte', 'pendiente');
    }

    public function scopeEscalados($query)
    {
        return $query->where('estado_reporte', 'escalado');
    }
}
