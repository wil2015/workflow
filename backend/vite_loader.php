<?php
// backend/vite_loader.php

function carregar_cabecalho_vue() {
    // SEU AMBIENTE:
    // Como você está usando Docker e Vite na porta 5174, 
    // vamos deixar fixo o modo DESENVOLVIMENTO por enquanto.
    
    // Quando for para produção, mudaremos esta lógica.
    
    return '
        <script type="module" src="http://localhost:5174/@vite/client"></script>
        
        <script type="module" src="http://localhost:5174/src_novo/main.js"></script>
    ';
}
?>