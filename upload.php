<?php
// =============== CONFIGURAÇÕES BÁSICAS ===============
$uploadBase = __DIR__ . '/uploads';

// Garante que a pasta base exista
if (!is_dir($uploadBase)) {
    mkdir($uploadBase, 0775, true);
}

// Função simples de limpeza
function limpar($valor) {
    return trim(filter_var($valor ?? '', FILTER_SANITIZE_STRING));
}

// Coleta dados do POST
$empresa       = limpar($_POST['empresa']       ?? '');
$municipio     = limpar($_POST['municipio']     ?? '');
$responsavel   = limpar($_POST['responsavel']   ?? '');
$emailContato  = limpar($_POST['email_contato'] ?? '');
$telefone      = limpar($_POST['telefone']      ?? '');

// Lista de servidores (JSON)
$servidoresJson = $_POST['servidores_json'] ?? '[]';
$servidores     = json_decode($servidoresJson, true);
if (!is_array($servidores)) {
    $servidores = [];
}

// Cria uma pasta específica para este envio
$slugBase = preg_replace('/[^a-z0-9_-]/i', '_', $empresa ?: 'sem_nome');
$slugBase = substr($slugBase, 0, 40);
$dirEnvio = $uploadBase . '/' . date('Ymd_His') . '_' . $slugBase;

if (!is_dir($dirEnvio)) {
    mkdir($dirEnvio, 0775, true);
}

// Extensões permitidas
$extPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];

// =============== FUNÇÃO PARA SALVAR ARQUIVOS MÚLTIPLOS ===============
function salvarMultiplosArquivos($campo, $prefixo, $dirEnvio, $extPermitidas, &$arquivosSalvos) {
    if (empty($_FILES[$campo]['name'])) {
        return;
    }

    $names = $_FILES[$campo]['name'];
    $tmp   = $_FILES[$campo]['tmp_name'];
    $errs  = $_FILES[$campo]['error'];

    if (!is_array($names)) {
        $names = [$names];
        $tmp   = [$tmp];
        $errs  = [$errs];
    }

    foreach ($names as $i => $nomeOriginal) {
        if ($errs[$i] !== UPLOAD_ERR_OK || !$nomeOriginal) {
            continue;
        }

        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if (!in_array($ext, $extPermitidas)) {
            continue;
        }

        $nomeFinal = sprintf('%s_%02d.%s', $prefixo, $i+1, $ext);
        $destino   = $dirEnvio . '/' . $nomeFinal;

        if (move_uploaded_file($tmp[$i], $destino)) {
            $arquivosSalvos[] = $nomeFinal;
        }
    }
}

// =============== TRATAMENTO DOS ARQUIVOS ===============
$arquivosSalvos = [];

// 1) Arquivo único, se houver
if (isset($_FILES['doc_unico']) && $_FILES['doc_unico']['error'] === UPLOAD_ERR_OK) {
    $nomeOriginal = $_FILES['doc_unico']['name'];
    $tmpName      = $_FILES['doc_unico']['tmp_name'];
    $ext          = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

    if (in_array($ext, $extPermitidas)) {
        $nomeFinal = '00_arquivo_unico.' . $ext;
        $destino   = $dirEnvio . '/' . $nomeFinal;
        if (move_uploaded_file($tmpName, $destino)) {
            $arquivosSalvos[] = $nomeFinal;
        }
    }
}

// 2) Arquivos separados por tipo
salvarMultiplosArquivos('doc_pessoais',    '01_doc_pessoal',   $dirEnvio, $extPermitidas, $arquivosSalvos);
salvarMultiplosArquivos('doc_residencias', '02_residencia',    $dirEnvio, $extPermitidas, $arquivosSalvos);
salvarMultiplosArquivos('doc_certificados','03_certificado',   $dirEnvio, $extPermitidas, $arquivosSalvos);

// =============== REGISTRA UMA "FICHINHA" COM OS DADOS ===============
$info = [
    'data_envio'    => date('d/m/Y H:i'),
    'empresa'       => $empresa,
    'municipio'     => $municipio,
    'responsavel'   => $responsavel,
    'email_contato' => $emailContato,
    'telefone'      => $telefone,
    'servidores'    => $servidores,
    'arquivos'      => $arquivosSalvos,
];

file_put_contents($dirEnvio . '/dados_envio.txt', print_r($info, true));

// =============== (OPCIONAL) ENVIO DE E-MAIL SIMPLES ===============
// Se quiser receber um resumo por e-mail, descomente e configure:

/*
$para    = 'SEU_EMAIL@DOMINIO.COM';
$assunto = 'Novo envio - Portal CNES';
$mensagem  = "Novo envio recebido:\n\n";
$mensagem .= "Empresa: {$empresa}\n";
$mensagem .= "Município: {$municipio}\n";
$mensagem .= "Responsável: {$responsavel}\n";
$mensagem .= "E-mail: {$emailContato}\n";
$mensagem .= "Telefone: {$telefone}\n\n";
$mensagem .= "Quantidade de servidores: " . count($servidores) . "\n";
$mensagem .= "Pasta no servidor: {$dirEnvio}\n";

@mail($para, $assunto, $mensagem);
*/

// =============== RESPOSTA AO USUÁRIO ===============
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Envio concluído – Portal CNES</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="page">
    <div class="card">
      <div class="header">
        <h1 class="header-title">Envio concluído ✅</h1>
        <p class="header-sub">Os dados e documentos foram recebidos com sucesso.</p>
      </div>

      <div class="section">
        <h3>Resumo do envio</h3>
        <p><strong>Empresa:</strong> <?= htmlspecialchars($empresa) ?></p>
        <p><strong>Município:</strong> <?= htmlspecialchars($municipio) ?></p>
        <p><strong>Responsável:</strong> <?= htmlspecialchars($responsavel) ?></p>
        <p><strong>E-mail:</strong> <?= htmlspecialchars($emailContato) ?></p>
        <p><strong>Telefone:</strong> <?= htmlspecialchars($telefone) ?></p>
        <p><strong>Servidores cadastrados:</strong> <?= count($servidores) ?></p>

        <?php if (!empty($servidores)): ?>
          <ul>
          <?php foreach ($servidores as $s): ?>
            <li><?= htmlspecialchars($s['nome'] ?? '') ?> – <?= htmlspecialchars($s['cargo'] ?? '') ?></li>
          <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <p style="margin-top:8px;"><strong>Arquivos salvos:</strong></p>
        <?php if (!empty($arquivosSalvos)): ?>
          <ul>
          <?php foreach ($arquivosSalvos as $arq): ?>
            <li><?= htmlspecialchars($arq) ?></li>
          <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p><em>Nenhum arquivo foi salvo (verifique se pelo menos um documento foi anexado).</em></p>
        <?php endif; ?>

        <p style="margin-top:8px;font-size:0.8rem;color:#4b5563;">
          Os arquivos deste envio estão armazenados em:<br>
          <code><?= htmlspecialchars($dirEnvio) ?></code>
        </p>
      </div>

      <button class="btn btn-primary" onclick="window.location.href='index.html'">
        Fazer novo envio
      </button>
    </div>
  </div>
</body>
</html>
