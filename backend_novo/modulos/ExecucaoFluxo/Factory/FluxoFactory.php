<?php
require_once __DIR__ . '/../FluxoRepo.php';
require_once __DIR__ . '/../FluxoService.php';
require_once __DIR__ . '/../Strategy/ValidacaoPadrao.php';

class FluxoFactory {
    
    public static function criarService($pdo, $connSenior): FluxoService {
        // 1. Instancia o Repositório
        $repo = new FluxoRepo($pdo, $connSenior);

        // 2. Define a Estratégia de Validação (Aqui você poderia escolher outra estratégia baseado em config)
        $validador = new ValidacaoPadrao();

        // 3. Retorna o Service pronto com tudo injetado
        return new FluxoService($repo, $validador);
    }
}