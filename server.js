// server.js - Servidor Node.js com Puppeteer para resolver proteção
const express = require('express');
const puppeteer = require('puppeteer');
const app = express();
const port = 3000;

app.use(express.json());

// Rota para o health check do Koyeb
app.get('/health', (req, res) => {
    res.send('OK');
});

// Rota principal que faz a requisição com JavaScript
app.post('/lock', async (req, res) => {
    const { deviceId } = req.body;
    
    console.log(`[${new Date().toISOString()}] 🔄 Requisição recebida para device: ${deviceId}`);
    
    try {
        // Inicia o navegador (puppeteer)
        const browser = await puppeteer.launch({
            args: ['--no-sandbox', '--disable-setuid-sandbox'],
            headless: 'new'
        });
        
        const page = await browser.newPage();
        
        // Configurar headers para parecer um navegador real
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0');
        await page.setExtraHTTPHeaders({
            'Accept-Language': 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7'
        });
        
        // Interceptar requisições para debug
        page.on('response', async (response) => {
            const url = response.url();
            if (url.includes('lock.php')) {
                console.log(`[${new Date().toISOString()}] 📡 Resposta de ${url}: ${response.status()}`);
            }
        });
        
        console.log(`[${new Date().toISOString()}] 🌐 Acessando ${process.env.SITE_URL}/lock.php`);
        
        // Primeiro acesso para obter cookies
        await page.goto(`${process.env.SITE_URL}/lock.php`, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        // Esperar o JavaScript executar e redirecionar
        await page.waitForTimeout(3000);
        
        // Agora fazer o POST com os cookies
        const cookies = await page.cookies();
        console.log(`[${new Date().toISOString()}] 🍪 Cookies obtidos:`, cookies.length);
        
        // Fazer a requisição POST via página
        const resultado = await page.evaluate(async (deviceId) => {
            const response = await fetch('https://tipx-ag.kesug.com/lock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    device_id: deviceId,
                    acao: 'assumir'
                })
            });
            return await response.json();
        }, deviceId);
        
        await browser.close();
        
        console.log(`[${new Date().toISOString()}] ✅ Resposta:`, resultado);
        res.json(resultado);
        
    } catch (error) {
        console.error(`[${new Date().toISOString()}] ❌ Erro:`, error.message);
        res.status(500).json({ status: 'erro', mensagem: error.message });
    }
});

// Rota de teste simples
app.get('/teste', async (req, res) => {
    res.json({ status: 'funcionando' });
});

app.listen(port, () => {
    console.log(`[${new Date().toISOString()}] 🚀 Servidor rodando na porta ${port}`);
    console.log(`[${new Date().toISOString()}] 🌐 Site: ${process.env.SITE_URL}`);
});
