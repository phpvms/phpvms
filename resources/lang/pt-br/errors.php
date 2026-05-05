<?php

declare(strict_types=1);

return [
    401 => [
        'title'   => 'Acesso não autorizado',
        'message' => 'Bem, isso é embaraçoso, você não está autorizado a acessar ou executar esta função. '.
            'Clique <a href=":link">aqui</a> para voltar a pagina inicial.',
    ],
    404 => [
        'title'   => 'Pagina não encontrada',
        'message' => 'Bem, isso é embaraçoso, a página que você solicitou não existe. '.
            'Clique <a href=":link">aqui</a> para voltar a pagina inicial.',
    ],
    503 => [
        'title'   => 'Erro Interno',
        'message' => 'Um erro ocorreu',
    ],
];
