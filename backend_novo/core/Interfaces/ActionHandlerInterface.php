<?php
namespace App\Core\Interfaces;

interface ActionHandlerInterface
{
    /**
     * @param array $payload Dados extraídos da requisição (ex: id_processo, id_usuario)
     * @return mixed Resposta a ser convertida em JSON
     */
    public function handle(array $payload);
}