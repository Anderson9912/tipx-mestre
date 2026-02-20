<?php
// mestre_sintetico.php - Versão com pré-requisição GET para cookies
// Mestre artificial para rodar 24/7 no Koyeb

header('Content-Type: text/plain');
set_time_limit(0);
ignore_user_abort(true);

// ===== CONFIGURAÇÕES =====
define('SITE_URL', 'https://tipx-ag.kesug.com');
define('TELEGRAM_TOKEN', '8459706438:AAEIhrkXEago037KTMGPk2qisGQJBelfawQ');
define('CHECK_INTERVAL', 10); // segundos entre ciclos
define('HEARTBEAT_INTERVAL', 25); // segundos entre heartbeats

$deviceId = 'mestre_sintetico_koyeb_' . gethostname() . '_' . uniqid();
$ultimoHeartbeat = 0;
$ciclo = 0;

echo "🚀 MESTRE SINTÉTICO INICIADO\n";
echo "📱 Device ID: $deviceId\n";
echo "🌐 Site: " . SITE_URL . "\n";
echo "⏱️  Intervalo: " . CHECK_INTERVAL . "s\n\n";

// Loop infinito
while (true) {
    $ciclo++;
    $inicio = time();
    $timestamp = date('Y-m-d H:i:s');
    
    try {
        // PASSO 1: Verificar/assumir como mestre
        if ($ciclo == 1 || ($inicio - $ultimoHeartbeat) >= HEARTBEAT_INTERVAL) {
            echo "[$timestamp] 🔄 Tentando ser mestre...\n";
            $lock = tentarSerMestre($deviceId);
            
            echo "[$timestamp] 📡 Resposta completa: " . json_encode($lock) . "\n";
            
            if ($lock && isset($lock['status'])) {
                if ($lock['status'] === 'mestre') {
                    echo "[$timestamp] ✅ SOU O MESTRE SINTÉTICO!\n";
                    $ultimoHeartbeat = $inicio;
                } elseif ($lock['status'] === 'ativo' || $lock['status'] === 'escravo') {
                    $mestreAtual = $lock['mestre'] ?? 'desconhecido';
                    echo "[$timestamp] 👤 Já existe mestre ativo: $mestreAtual\n";
                    echo "[$timestamp] ⏳ Aguardando 30s...\n";
                    sleep(30);
                    continue;
                } elseif ($lock['status'] === 'disponivel') {
                    echo "[$timestamp] 📭 Nenhum mestre ativo. Vou assumir!\n";
                    // Tenta novamente no próximo ciclo
                } else {
                    echo "[$timestamp] ⚠️ Status inesperado: {$lock['status']}\n";
                    sleep(30);
                    continue;
                }
            } else {
                echo "[$timestamp] ❌ Resposta inválida do servidor\n";
                sleep(30);
                continue;
            }
        }
        
        // PASSO 2: Buscar dados da API
        echo "[$timestamp] 🔄 Ciclo $ciclo - Buscando dados da API...\n";
        $dadosAPI = buscarDadosAPI();
        
        if ($dadosAPI && isset($dadosAPI['resultados']) && count($dadosAPI['resultados']) > 0) {
            $qtd = count($dadosAPI['resultados']);
            echo "[$timestamp] 📊 Recebidas $qtd rodadas\n";
            
            // Calcular porcentagens
            $porcentagens = calcularPorcentagens($dadosAPI['resultados']);
            echo "[$timestamp] 📈 50 rodadas: {$porcentagens['p50']}% | 25 rodadas: {$porcentagens['p25']}%\n";
            
            // Salvar no gráfico
            if (salvarDadosGrafico($porcentagens)) {
                echo "[$timestamp] ✅ Gráfico atualizado\n";
            } else {
                echo "[$timestamp] ⚠️ Falha ao atualizar gráfico\n";
            }
            
            // Verificar alertas
            $alertasEnviados = verificarAlertas($dadosAPI['resultados']);
            if ($alertasEnviados > 0) {
                echo "[$timestamp] 🔔 Alertas enviados: $alertasEnviados\n";
            }
        } else {
            echo "[$timestamp] ⚠️ API retornou sem dados\n";
        }
        
    } catch (Exception $e) {
        echo "[$timestamp] ❌ ERRO: " . $e->getMessage() . "\n";
    }
    
    // Aguardar até próximo ciclo
    $fim = time();
    $tempoExecucao = $fim - $inicio;
    $espera = max(1, CHECK_INTERVAL - $tempoExecucao);
    
    echo "[$timestamp] ⏱️ Ciclo completo em {$tempoExecucao}s, próximo em {$espera}s\n\n";
    sleep($espera);
}

// ============ FUNÇÕES ============

function tentarSerMestre($deviceId) {
    $urlBase = SITE_URL . '/lock.php';
    
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Upgrade-Insecure-Requests: 1'
    ];
    
    // PASSO 1: Fazer requisição GET para obter os cookies de proteção
    echo "[LOCK] Obtendo cookies de proteção (GET)...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlBase);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // Capturar headers para ver cookies
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
    
    $respostaGet = curl_exec($ch);
    $httpCodeGet = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "[LOCK] GET - Código HTTP: $httpCodeGet\n";
    
    // PASSO 2: Agora faz a requisição POST com os cookies obtidos
    echo "[LOCK] Enviando requisição POST com cookies...\n";
    
    $postData = json_encode([
        'device_id' => $deviceId,
        'acao' => 'assumir'
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlBase);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
    
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    
    echo "[LOCK] POST - Código HTTP: $httpCode\n";
    
    if ($erro) {
        echo "[LOCK] Erro cURL: $erro\n";
    }
    
    if ($resposta) {
        // Tenta interpretar como JSON
        $dados = json_decode($resposta, true);
        if ($dados && isset($dados['status'])) {
            echo "[LOCK] JSON recebido: " . json_encode($dados) . "\n";
            return $dados;
        }
        
        // Se não for JSON, mostra os primeiros 200 caracteres
        echo "[LOCK] Resposta não-JSON (início): " . substr($resposta, 0, 200) . "\n";
    }
    
    return ['status' => 'erro', 'http' => $httpCode, 'erro_curl' => $erro];
}

function buscarDadosAPI() {
    $url = SITE_URL . '/proxy.php';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0');
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
    
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $resposta) {
        return json_decode($resposta, true);
    }
    
    return null;
}

function calcularPorcentagens($resultados) {
    $ultimas50 = array_slice($resultados, 0, 50);
    $ultimas25 = array_slice($resultados, 0, 25);
    
    $contagem50 = 0;
    $contagem25 = 0;
    
    foreach ($ultimas50 as $item) {
        if (isset($item['multiplicador']) && floatval($item['multiplicador']) >= 2.00) {
            $contagem50++;
        }
    }
    
    foreach ($ultimas25 as $item) {
        if (isset($item['multiplicador']) && floatval($item['multiplicador']) >= 2.00) {
            $contagem25++;
        }
    }
    
    $p50 = count($ultimas50) > 0 ? round(($contagem50 / count($ultimas50)) * 100, 1) : 0;
    $p25 = count($ultimas25) > 0 ? round(($contagem25 / count($ultimas25)) * 100, 1) : 0;
    
    return [
        'p50' => $p50,
        'p25' => $p25,
        'timestamp' => date('H:i')
    ];
}

function salvarDadosGrafico($porcentagens) {
    $url = SITE_URL . '/grafico_data.php';
    
    // Buscar dados existentes
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0');
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
    
    $dadosExistentes = curl_exec($ch);
    curl_close($ch);
    
    $dados = json_decode($dadosExistentes, true) ?: [
        'timestamps' => [],
        'porcentagens50' => [],
        'porcentagens25' => []
    ];
    
    // Adicionar novo ponto
    array_unshift($dados['timestamps'], $porcentagens['timestamp']);
    array_unshift($dados['porcentagens50'], $porcentagens['p50']);
    array_unshift($dados['porcentagens25'], $porcentagens['p25']);
    
    // Manter apenas 90 pontos
    if (count($dados['timestamps']) > 90) {
        array_splice($dados['timestamps'], 90);
        array_splice($dados['porcentagens50'], 90);
        array_splice($dados['porcentagens25'], 90);
    }
    
    // Salvar
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'device_id' => 'mestre_sintetico',
        'dados' => $dados
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0');
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
    
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200;
}

function verificarAlertas($resultados) {
    $url = SITE_URL . '/listar_usuarios.php';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0');
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
    
    $resposta = curl_exec($ch);
    curl_close($ch);
    
    $usuarios = json_decode($resposta, true) ?: ['usuarios' => []];
    $alertasEnviados = 0;
    
    foreach ($usuarios['usuarios'] as $usuario) {
        if (empty($usuario['config_alerta']) || empty($usuario['config_alerta']['ativo'])) {
            continue;
        }
        
        $config = $usuario['config_alerta'];
        $deveAlertar = false;
        
        // Critério 1: % de >=2.00x
        if (!empty($config['criterio1']['rodadas']) && !empty($config['criterio1']['porcentagem'])) {
            $rodadas = min($config['criterio1']['rodadas'], count($resultados));
            $amostra = array_slice($resultados, 0, $rodadas);
            $contagem = 0;
            foreach ($amostra as $item) {
                if (floatval($item['multiplicador']) >= 2.00) $contagem++;
            }
            $porcentagem = ($contagem / $rodadas) * 100;
            if ($porcentagem >= $config['criterio1']['porcentagem']) {
                $deveAlertar = true;
            }
        }
        
        if ($deveAlertar) {
            if (enviarAlerta($usuario['telegram_chat_id'])) {
                $alertasEnviados++;
            }
        }
    }
    
    return $alertasEnviados;
}

function enviarAlerta($chatId) {
    $mensagem = "🚨𝗔𝗣𝗢𝗦𝗧𝗔 𝗚𝗔𝗡𝗛𝗔🚨\n\nINICIO DE UM POSSÍVEL PAGUE✅️\n\nhttps://apostaganha.bet.br/cassino/jogos/aviator";
    
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatId,
        'text' => $mensagem,
        'parse_mode' => 'Markdown'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0');
    
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200;
}
?>
