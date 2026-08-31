document.addEventListener('DOMContentLoaded', function() {
    // Elementos do DOM
    const uploadForm = document.getElementById('uploadForm');
    const csvFileInput = document.getElementById('csvFile');
    const dryRunButton = document.getElementById('dryRunButton');
    const progressSection = document.querySelector('.progress-section');
    const resultsSection = document.querySelector('.results-section');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const stepText = document.getElementById('stepText');
    const toCreateCount = document.getElementById('toCreateCount');
    const toDeleteCount = document.getElementById('toDeleteCount');
    const processedCount = document.getElementById('processedCount');
    const statusText = document.getElementById('statusText');
    const logContent = document.getElementById('logContent');
    const createdCount = document.getElementById('createdCount');
    const deletedCount = document.getElementById('deletedCount');
    const errorCount = document.getElementById('errorCount');
    const timeElapsed = document.getElementById('timeElapsed');
    const createdItems = document.getElementById('createdItems');
    const deletedItems = document.getElementById('deletedItems');
    const errorItems = document.getElementById('errorItems');
    const downloadLogBtn = document.getElementById('downloadLogBtn');
    const resetBtn = document.getElementById('resetBtn');
    const delimiterSelect = document.getElementById('delimiterSelect');
    
    const fileLabelText = document.getElementById('fileLabelText');
    const fileSelectedInfo = document.getElementById('fileSelectedInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const clearFileBtn = document.getElementById('clearFileBtn');
    
    const tabBtns = document.querySelectorAll('.tab-btn');
    
    // Configurações
    const bitrixConfig = {
        webhook: 'https://aymoresembalagens.bitrix24.com.br/rest/1/pn4tjn73hsatd6dv/',
        spaId: 1076,
        stageId: 'DT1076_29:NEW',
        fieldMapping: {
            'Razão Social': 'ufCrm21_1766496262',
            'E-mail para Envio': 'ufCrm21_1766496286',
            'Data de Vencimento': 'ufCrm21_1766497551',
            'Nº Título': 'ufCrm21_1766497570',
            'Empresa Emissora': 'ufCrm21_1766497609',
            'Montante': 'ufCrm21_1766498122',
            'Liquidado?': 'ufCrm21_1766498157',
            'Telefone': 'ufCrm21_1767100434'
        },
        requestDelay: 350
    };
    
    const empresaEmissoraMapping = {
        'Aymorés Embalagens': '36647',
        'HIMAYA SA ATACADISTA': '36649',
        'Proteus Matriz': '36651',
        'Proteus Atacadista': '36653'
    };
    
    // Estado da sincronização
    let syncData = {
        startTime: null,
        endTime: null,
        csvData: [],
        itemsToCreate: [],
        itemsToDelete: [],
        createdItems: [],
        deletedItems: [],
        errors: [],
        isDryRun: false,
        totalSteps: 0,
        completedSteps: 0,
        csvDelimiter: ';',
        cachedBitrixItems: new Map()
    };
    
    // Event Listeners
    csvFileInput.addEventListener('change', handleFileSelect);
    clearFileBtn.addEventListener('click', clearFile);
    uploadForm.addEventListener('submit', handleSubmit);
    dryRunButton.addEventListener('click', handleDryRun);
    resetBtn.addEventListener('click', resetSystem);
    downloadLogBtn.addEventListener('click', downloadLog);
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            tabBtns.forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
            
            if (tabId === 'history' && window.historyManager) {
                window.historyManager.updateHistoryDisplay();
            }
        });
    });
    
    // Funções principais
    function handleFileSelect(e) {
        const file = this.files[0];
        if (file) {
            fileName.textContent = file.name;
            const sizeInKB = (file.size / 1024).toFixed(1);
            fileSize.textContent = `(${sizeInKB} KB)`;
            fileSelectedInfo.classList.remove('hidden');
            fileLabelText.innerHTML = '<i class="fas fa-check-circle file-success"></i> Arquivo Selecionado';
            document.querySelector('.file-label').classList.add('file-selected');
        }
    }
    
    function clearFile() {
        csvFileInput.value = '';
        hideFileInfo();
    }
    
    function hideFileInfo() {
        fileSelectedInfo.classList.add('hidden');
        fileLabelText.innerHTML = '<i class="fas fa-file-csv"></i> Escolher arquivo CSV';
        document.querySelector('.file-label').classList.remove('file-selected');
    }
    
    function handleSubmit(e) {
        e.preventDefault();
        if (!validateForm()) return;
        startSync(false);
    }
    
    function handleDryRun() {
        if (!validateForm()) return;
        startSync(true);
    }
    
    function validateForm() {
        if (!csvFileInput.files[0]) {
            alert('Por favor, selecione um arquivo CSV.');
            return false;
        }
        
        if (!delimiterSelect.value) {
            showValidationError(delimiterSelect, 'Por favor, selecione o separador do arquivo CSV.');
            return false;
        }
        
        return true;
    }
    
    function showValidationError(element, message) {
        addLog('error', message);
        element.classList.add('error', 'shake');
        element.focus();
        
        setTimeout(() => element.classList.remove('shake'), 400);
        setTimeout(() => element.classList.remove('error'), 2000);
    }
    
    async function startSync(isDryRun) {
        const file = csvFileInput.files[0];
        const delimiter = delimiterSelect.value;
        
        // Resetar estado
        resetSyncData(isDryRun);
        
        addLog('info', `📂 Processando arquivo: ${file.name}`);
        progressSection.classList.remove('hidden');
        resultsSection.classList.add('hidden');
        
        try {
            syncData.startTime = new Date();
            
            // 1. Carregar cache do servidor
            addLog('info', '📋 Carregando cache do servidor...');
            await loadBitrixCache();
            
            // 2. Processar CSV
            addLog('info', '📄 Lendo arquivo CSV...');
            const csvText = await readFileAsText(file);
            
            // Validar o separador ANTES de processar
            const isValidDelimiter = await validateCSVDelimiter(csvText, delimiter);
            if (!isValidDelimiter) {
                throw new Error(`O separador "${getDelimiterName(delimiter)}" parece estar incorreto para este arquivo. Por favor, selecione outro separador.`);
            }
            
            await parseCSV(csvText, delimiter);
            
            // 3. Comparar dados
            addLog('info', '🔍 Comparando dados...');
            compareData();
            
            // 4. Executar ações
            if (isDryRun || (syncData.itemsToCreate.length === 0 && syncData.itemsToDelete.length === 0)) {
                showResults();
            } else {
                syncData.totalSteps = syncData.itemsToCreate.length + syncData.itemsToDelete.length;
                updateProgress();
                await executeActions();
            }
            
        } catch (error) {
            addLog('error', `❌ Erro na sincronização: ${error.message}`);
            statusText.textContent = 'Erro no processamento';
            
            // Parar o processo e mostrar erro específico do separador
            if (error.message.includes('separador')) {
                showValidationError(delimiterSelect, error.message);
                progressSection.classList.add('hidden');
            }
        }
    }
    
    function getDelimiterName(delimiter) {
        switch(delimiter) {
            case ';': return 'Ponto e vírgula (;)';
            case ',': return 'Vírgula (,)';
            case '\t': return 'Tabulação';
            case 'auto': return 'Automático';
            default: return delimiter;
        }
    }
    
    async function validateCSVDelimiter(csvText, selectedDelimiter) {
        const lines = csvText.split(/\r\n|\r|\n/).filter(line => line.trim() !== '');
        if (lines.length < 2) {
            throw new Error('Arquivo CSV vazio ou inválido');
        }
        
        // Obter a primeira linha não vazia (cabeçalho)
        const firstLine = lines[0];
        
        // Função para contar campos com um delimitador específico
        function countFields(line, delimiter) {
            if (delimiter === '\t') {
                return line.split('\t').length;
            }
            
            let count = 1;
            let inQuotes = false;
            
            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                const nextChar = line[i + 1];
                
                if (char === '"') {
                    if (inQuotes && nextChar === '"') {
                        i++; // Pular próxima aspa
                    } else {
                        inQuotes = !inQuotes;
                    }
                } else if (char === delimiter && !inQuotes) {
                    count++;
                }
            }
            
            return count;
        }
        
        // Se for automático, detectar o melhor delimitador
        if (selectedDelimiter === 'auto') {
            const delimiters = [';', ',', '\t'];
            let bestDelimiter = '';
            let maxFields = 0;
            
            for (const delim of delimiters) {
                const fieldCount = countFields(firstLine, delim);
                if (fieldCount > maxFields) {
                    maxFields = fieldCount;
                    bestDelimiter = delim;
                }
            }
            
            // Verificar se encontrou um delimitador válido (pelo menos 2 campos)
            if (maxFields >= 2) {
                syncData.csvDelimiter = bestDelimiter;
                addLog('info', `✅ Delimitador detectado: ${getDelimiterName(bestDelimiter)} (${maxFields} campos)`);
                return true;
            } else {
                throw new Error('Não foi possível detectar o separador do arquivo CSV. Verifique o formato do arquivo.');
            }
        }
        
        // Se for um delimitador específico, validar
        const fieldCount = countFields(firstLine, selectedDelimiter);
        
        // Verificar se o delimitador produz pelo menos 2 campos
        if (fieldCount >= 2) {
            syncData.csvDelimiter = selectedDelimiter;
            addLog('info', `✅ Delimitador válido: ${getDelimiterName(selectedDelimiter)} (${fieldCount} campos)`);
            return true;
        } else {
            // Sugerir outro delimitador se possível
            const delimiters = [';', ',', '\t'];
            const suggestions = [];
            
            for (const delim of delimiters) {
                if (delim !== selectedDelimiter) {
                    const count = countFields(firstLine, delim);
                    if (count >= 2) {
                        suggestions.push(`${getDelimiterName(delim)} (${count} campos)`);
                    }
                }
            }
            
            let errorMsg = `O separador "${getDelimiterName(selectedDelimiter)}" parece estar incorreto (apenas ${fieldCount} campo(s) detectado(s)).`;
            
            if (suggestions.length > 0) {
                errorMsg += ` Sugestões: ${suggestions.join(', ')}`;
            }
            
            throw new Error(errorMsg);
        }
    }
    
    function resetSyncData(isDryRun) {
        syncData = {
            startTime: null,
            endTime: null,
            csvData: [],
            itemsToCreate: [],
            itemsToDelete: [],
            createdItems: [],
            deletedItems: [],
            errors: [],
            isDryRun: isDryRun,
            totalSteps: 0,
            completedSteps: 0,
            csvDelimiter: delimiterSelect.value === 'auto' ? ';' : delimiterSelect.value,
            cachedBitrixItems: syncData.cachedBitrixItems
        };
        
        progressFill.style.width = '0%';
        progressText.textContent = '0%';
        stepText.textContent = 'Iniciando...';
        statusText.textContent = 'Processando arquivo CSV...';
        
        logContent.innerHTML = '';
        toCreateCount.textContent = '0';
        toDeleteCount.textContent = '0';
        processedCount.textContent = '0';
    }
    
    async function loadBitrixCache() {
        try {
            const response = await fetch('bitrix-cache.php?action=load');
            const data = await response.json();
            
            if (data.success) {
                syncData.cachedBitrixItems.clear();
                data.items.forEach(item => {
                    const key = normalizeTitulo(item.titulo);
                    if (key && item.id) {
                        syncData.cachedBitrixItems.set(key, {
                            id: item.id,
                            titulo: item.titulo
                        });
                    }
                });
                addLog('success', `✅ ${syncData.cachedBitrixItems.size} itens carregados do cache`);
            } else {
                throw new Error(data.message || 'Erro ao carregar cache');
            }
        } catch (error) {
            addLog('warning', `⚠️ Não foi possível carregar o cache: ${error.message}`);
            addLog('info', 'ℹ️ Iniciando com cache vazio');
            syncData.cachedBitrixItems.clear();
        }
    }
    
    async function saveBitrixCache() {
        try {
            const items = Array.from(syncData.cachedBitrixItems.values());
            const response = await fetch('bitrix-cache.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items: items })
            });
            
            const data = await response.json();
            if (data.success) {
                addLog('success', `✅ Cache salvo com ${items.length} itens`);
                return true;
            } else {
                throw new Error(data.message || 'Erro ao salvar cache');
            }
        } catch (error) {
            addLog('error', `❌ Erro ao salvar cache: ${error.message}`);
            return false;
        }
    }
    
    function readFileAsText(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = (e) => reject(new Error('Erro ao ler arquivo'));
            reader.readAsText(file, 'ISO-8859-1');
        });
    }
    
    async function parseCSV(csvText, delimiter) {
        const lines = csvText.split(/\r\n|\r|\n/).filter(line => line.trim() !== '');
        if (lines.length < 2) {
            throw new Error('Arquivo CSV vazio ou inválido');
        }
        
        // Usar o delimitador já validado
        const actualDelimiter = syncData.csvDelimiter;
        addLog('info', `🔧 Usando delimitador: ${getDelimiterName(actualDelimiter)}`);
        
        // Parse CSV manual
        const headers = parseCSVLine(lines[0], actualDelimiter);
        const data = [];
        
        // Verificar se temos cabeçalhos suficientes
        if (headers.length < 2) {
            throw new Error(`Apenas ${headers.length} coluna(s) detectada(s) com o separador "${getDelimiterName(actualDelimiter)}". Verifique se o separador está correto.`);
        }
        
        addLog('info', `📊 Cabeçalhos detectados: ${headers.length} colunas`);
        
        for (let i = 1; i < lines.length; i++) {
            const values = parseCSVLine(lines[i], actualDelimiter);
            if (values.length === 0) continue;
            
            // Verificar consistência do número de colunas
            if (values.length !== headers.length) {
                addLog('warning', `⚠️ Linha ${i+1}: Número de colunas inconsistente (${values.length} vs ${headers.length})`);
            }
            
            const item = {};
            
            // Mapear campos básicos
            const tituloIndex = findHeaderIndex(headers, 'Nº Título');
            const razaoSocialIndex = findHeaderIndex(headers, 'Razão Social');
            const emailIndex = findHeaderIndex(headers, 'E-mail para Envio');
            const vencimentoIndex = findHeaderIndex(headers, 'Data de Vencimento');
            const empresaIndex = findHeaderIndex(headers, 'Empresa Emissora');
            const montanteIndex = findHeaderIndex(headers, 'Montante');
            const liquidadoIndex = findHeaderIndex(headers, 'Liquidado?');
            const telefone1Index = findHeaderIndex(headers, 'TELEFONE1');
            const telefone2Index = findHeaderIndex(headers, 'TELEFONE2');
            
            // Preencher item
            item['Nº Título'] = cleanText(tituloIndex >= 0 ? values[tituloIndex] : '');
            item['Razão Social'] = cleanText(razaoSocialIndex >= 0 ? values[razaoSocialIndex] : '');
            item['E-mail para Envio'] = cleanText(emailIndex >= 0 ? values[emailIndex] : '');
            item['Data de Vencimento'] = formatDate(cleanText(vencimentoIndex >= 0 ? values[vencimentoIndex] : ''));
            item['Empresa Emissora'] = cleanText(empresaIndex >= 0 ? values[empresaIndex] : '');
            item['Montante'] = extractMontanteValue(montanteIndex >= 0 ? values[montanteIndex] : '', actualDelimiter);
            item['Liquidado?'] = cleanText(liquidadoIndex >= 0 ? values[liquidadoIndex] : '');
            
            // Processar telefone
            const tel1 = telefone1Index >= 0 ? values[telefone1Index] : '';
            const tel2 = telefone2Index >= 0 ? values[telefone2Index] : '';
            item['Telefone'] = processPhoneNumber(tel1, tel2);
            
            if (item['Nº Título']) {
                data.push(item);
            }
        }
        
        syncData.csvData = data;
        addLog('success', `✅ ${data.length} itens válidos encontrados no CSV`);
    }
    
    function parseCSVLine(line, delimiter) {
        const result = [];
        let current = '';
        let inQuotes = false;
        
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            const nextChar = line[i + 1];
            
            if (char === '"') {
                if (inQuotes && nextChar === '"') {
                    current += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (char === delimiter && !inQuotes) {
                result.push(current);
                current = '';
            } else {
                current += char;
            }
        }
        
        result.push(current);
        return result.map(field => field.trim());
    }
    
    function findHeaderIndex(headers, searchTerm) {
        for (let i = 0; i < headers.length; i++) {
            if (cleanText(headers[i]).toLowerCase().includes(searchTerm.toLowerCase())) {
                return i;
            }
        }
        return -1;
    }
    
    function cleanText(text) {
        if (!text) return '';
        
        const replacements = {
            'Ã£': 'ã', 'Ãº': 'ú', 'Ã©': 'é', 'Ã³': 'ó', 'Ã': 'ã',
            'Ã¡': 'á', 'Ã§': 'ç', 'Ãª': 'ê', 'Ãµ': 'õ', 'Ã±': 'ñ'
        };
        
        let cleaned = text.toString();
        Object.keys(replacements).forEach(key => {
            cleaned = cleaned.replace(new RegExp(key, 'g'), replacements[key]);
        });
        
        cleaned = cleaned.replace(/[^\x20-\x7E\xA0-\xFF]/g, '');
        
        return cleaned.trim();
    }
    
    function formatDate(dateStr) {
        if (!dateStr) return '';
        
        const parts = dateStr.split('/');
        if (parts.length === 3) {
            const day = parts[0].padStart(2, '0');
            const month = parts[1].padStart(2, '0');
            const year = parts[2];
            return `${year}-${month}-${day}`;
        }
        
        return dateStr;
    }
    
    function extractMontanteValue(value, delimiter) {
        if (!value) return '0';
        
        let cleaned = value.toString().trim();
        
        if (delimiter === ',') {
            cleaned = cleaned.replace(/[^\d,.-]/g, '');
            cleaned = cleaned.replace(/\./g, '').replace(',', '.');
        } else {
            cleaned = cleaned.replace(/\./g, '').replace(',', '.');
        }
        
        const num = parseFloat(cleaned);
        return isNaN(num) ? '0' : num.toFixed(2);
    }
    
    function processPhoneNumber(phone1, phone2) {
        const extractDigits = (str) => (str || '').replace(/\D/g, '');
        
        let phone1Digits = extractDigits(phone1);
        let phone2Digits = extractDigits(phone2);
        
        const isMobile = (digits) => {
            if (digits.length === 11) {
                return digits.charAt(2) === '9';
            } else if (digits.length === 10) {
                const ddd = digits.substring(0, 2);
                const prefix = digits.substring(2, 5);
                return prefix.startsWith('9') || ['96', '97', '98', '99'].includes(prefix.substring(0, 2));
            }
            return false;
        };
        
        if (isMobile(phone1Digits)) return '+55' + phone1Digits;
        if (isMobile(phone2Digits)) return '+55' + phone2Digits;
        if (phone1Digits.length >= 10) return '+55' + phone1Digits;
        if (phone2Digits.length >= 10) return '+55' + phone2Digits;
        
        return '';
    }
    
    function normalizeTitulo(titulo) {
        if (!titulo) return '';
        return cleanText(titulo.toString())
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '');
    }
    
    function compareData() {
        const csvTitles = new Set();
        
        // 1. Identificar itens para criar (estão no CSV mas não no cache)
        syncData.itemsToCreate = syncData.csvData.filter(csvItem => {
            const titulo = csvItem['Nº Título'];
            const key = normalizeTitulo(titulo);
            
            if (!key) return false;
            
            csvTitles.add(key);
            
            if (!syncData.cachedBitrixItems.has(key)) {
                addLog('info', `➕ Para criar: "${titulo}" (não está no cache)`);
                return true;
            }
            
            addLog('info', `✅ Já existe: "${titulo}" - ignorado`);
            return false;
        });
        
        // 2. Identificar itens para excluir (estão no cache mas não no CSV)
        syncData.itemsToDelete = Array.from(syncData.cachedBitrixItems.values())
            .filter(cachedItem => {
                const key = normalizeTitulo(cachedItem.titulo);
                return !csvTitles.has(key);
            });
        
        syncData.itemsToDelete.forEach(item => {
            addLog('info', `➖ Para excluir: "${item.titulo}" (ID: ${item.id})`);
        });
        
        // Atualizar contadores
        toCreateCount.textContent = syncData.itemsToCreate.length;
        toDeleteCount.textContent = syncData.itemsToDelete.length;
        
        addLog('info', `📊 Análise: ${syncData.itemsToCreate.length} para criar, ${syncData.itemsToDelete.length} para excluir`);
    }
    
    async function executeActions() {
        // Processar criações
        for (let i = 0; i < syncData.itemsToCreate.length; i++) {
            await createBitrixItem(syncData.itemsToCreate[i]);
            syncData.completedSteps++;
            updateProgress();
            await delay(bitrixConfig.requestDelay);
        }
        
        // Processar exclusões
        for (let i = 0; i < syncData.itemsToDelete.length; i++) {
            await deleteBitrixItem(syncData.itemsToDelete[i]);
            syncData.completedSteps++;
            updateProgress();
            await delay(bitrixConfig.requestDelay);
        }
        
        syncData.endTime = new Date();
        
        // Salvar cache atualizado
        await saveBitrixCache();
        
        showResults();
    }
    
    async function createBitrixItem(csvItem) {
        try {
            const fields = {
                TITLE: csvItem['Razão Social'] || 'Item sem título',
                STAGE_ID: bitrixConfig.stageId
            };
            
            // Mapear campos
            Object.keys(bitrixConfig.fieldMapping).forEach(csvField => {
                let value = csvItem[csvField] || '';
                
                if (csvField === 'Empresa Emissora') {
                    value = empresaEmissoraMapping[value] || value;
                }
                
                if (value !== '') {
                    fields[bitrixConfig.fieldMapping[csvField]] = value;
                }
            });
            
            // Chamada para process.php
            const response = await fetch('process.php?action=createItem', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    entityTypeId: bitrixConfig.spaId,
                    fields: fields
                })
            });
            
            const result = await response.json();
            
            if (result.success && result.id) {
                // Adicionar ao cache local
                const key = normalizeTitulo(csvItem['Nº Título']);
                if (key) {
                    syncData.cachedBitrixItems.set(key, {
                        id: result.id,
                        titulo: csvItem['Nº Título']
                    });
                }
                
                syncData.createdItems.push({
                    csvItem: csvItem,
                    bitrixId: result.id,
                    title: csvItem['Nº Título'],
                    razaoSocial: csvItem['Razão Social']
                });
                
                addLog('success', `✅ Criado: "${csvItem['Nº Título']}" (ID: ${result.id})`);
            } else {
                throw new Error(result.message || 'Erro desconhecido');
            }
            
        } catch (error) {
            syncData.errors.push({
                type: 'create',
                item: csvItem['Nº Título'],
                razaoSocial: csvItem['Razão Social'],
                message: error.message
            });
            addLog('error', `❌ Erro ao criar "${csvItem['Nº Título']}": ${error.message}`);
        }
    }
    
    async function deleteBitrixItem(cachedItem) {
        try {
            const response = await fetch('process.php?action=deleteItem', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    entityTypeId: bitrixConfig.spaId,
                    id: cachedItem.id
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Remover do cache local
                const key = normalizeTitulo(cachedItem.titulo);
                syncData.cachedBitrixItems.delete(key);
                
                syncData.deletedItems.push({
                    title: cachedItem.titulo,
                    id: cachedItem.id
                });
                
                addLog('info', `🗑️ Excluído: "${cachedItem.titulo}" (ID: ${cachedItem.id})`);
            } else {
                throw new Error(result.message || 'Erro desconhecido');
            }
            
        } catch (error) {
            syncData.errors.push({
                type: 'delete',
                item: cachedItem.titulo,
                id: cachedItem.id,
                message: error.message
            });
            addLog('error', `❌ Erro ao excluir "${cachedItem.titulo}": ${error.message}`);
        }
    }
    
    function updateProgress() {
        if (syncData.totalSteps === 0) return;
        
        const percent = Math.round((syncData.completedSteps / syncData.totalSteps) * 100);
        progressFill.style.width = `${percent}%`;
        progressText.textContent = `${percent}%`;
        processedCount.textContent = syncData.completedSteps;
        
        stepText.textContent = `Processando ${syncData.completedSteps} de ${syncData.totalSteps}...`;
        statusText.textContent = syncData.completedSteps < syncData.totalSteps ? 'Sincronizando...' : 'Concluído';
    }
    
    function showResults() {
        const duration = syncData.endTime ? 
            ((syncData.endTime - syncData.startTime) / 1000).toFixed(1) : '0.0';
        
        // Atualizar resumo
        createdCount.textContent = syncData.createdItems.length;
        deletedCount.textContent = syncData.deletedItems.length;
        errorCount.textContent = syncData.errors.length;
        timeElapsed.textContent = `${duration}s`;
        
        // Atualizar tabelas
        updateResultsTables();
        
        // Mostrar resultados
        progressSection.classList.add('hidden');
        resultsSection.classList.remove('hidden');
        
        addLog('success', `🏁 Sincronização ${syncData.isDryRun ? 'simulada' : 'concluída'} em ${duration} segundos`);
        
        // Registrar no histórico se não for dry run
        if (!syncData.isDryRun && window.historyManager) {
            const record = {
                date: new Date().toISOString(),
                formattedDate: new Date().toLocaleDateString('pt-BR') + ' ' + 
                              new Date().toLocaleTimeString('pt-BR'),
                itemsCreated: syncData.createdItems.length,
                itemsDeleted: syncData.deletedItems.length,
                duration: duration,
                errors: syncData.errors.length
            };
            
            window.historyManager.addImportRecord(record);
        }
    }
    
    function updateResultsTables() {
        // Itens criados
        createdItems.innerHTML = '';
        syncData.createdItems.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.title}</td>
                <td>${item.razaoSocial}</td>
                <td>${item.csvItem['Data de Vencimento']}</td>
                <td>${formatMonetaryDisplay(item.csvItem['Montante'])}</td>
                <td>${item.csvItem['Telefone'] || ''}</td>
                <td><span class="status-badge success">Criado (ID: ${item.bitrixId})</span></td>
            `;
            createdItems.appendChild(row);
        });
        
        // Itens excluídos
        deletedItems.innerHTML = '';
        syncData.deletedItems.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.title}</td>
                <td>${item.id}</td>
                <td><span class="status-badge danger">Excluído</span></td>
            `;
            deletedItems.appendChild(row);
        });
        
        // Erros
        errorItems.innerHTML = '';
        syncData.errors.forEach(error => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${error.type === 'create' ? 'Criação' : 'Exclusão'}</td>
                <td>${error.item || error.id}</td>
                <td>${error.message}</td>
            `;
            errorItems.appendChild(row);
        });
    }
    
    function formatMonetaryDisplay(value) {
        const num = parseFloat(value);
        if (isNaN(num)) return '0,00';
        return num.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    function resetSystem() {
        uploadForm.reset();
        delimiterSelect.value = '';
        progressSection.classList.add('hidden');
        resultsSection.classList.add('hidden');
        hideFileInfo();
        
        logContent.innerHTML = '';
        createdItems.innerHTML = '';
        deletedItems.innerHTML = '';
        errorItems.innerHTML = '';
        
        // Resetar apenas dados da sincronização, mantendo cache
        syncData.csvData = [];
        syncData.itemsToCreate = [];
        syncData.itemsToDelete = [];
        syncData.createdItems = [];
        syncData.deletedItems = [];
        syncData.errors = [];
        syncData.isDryRun = false;
        syncData.totalSteps = 0;
        syncData.completedSteps = 0;
        
        toCreateCount.textContent = '0';
        toDeleteCount.textContent = '0';
        processedCount.textContent = '0';
        statusText.textContent = 'Aguardando início';
    }
    
    function downloadLog() {
        const logData = {
            timestamp: new Date().toISOString(),
            summary: {
                created: syncData.createdItems.length,
                deleted: syncData.deletedItems.length,
                errors: syncData.errors.length
            },
            createdItems: syncData.createdItems,
            deletedItems: syncData.deletedItems,
            errors: syncData.errors,
            csvData: syncData.csvData,
            cachedBitrixItems: Array.from(syncData.cachedBitrixItems.values())
        };
        
        const dataStr = JSON.stringify(logData, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        const url = URL.createObjectURL(dataBlob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = `bitrix-sync-log-${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
    function addLog(type, message) {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = document.createElement('div');
        logEntry.className = `log-entry ${type}`;
        
        let icon = '';
        switch(type) {
            case 'info': icon = '<i class="fas fa-info-circle"></i>'; break;
            case 'success': icon = '<i class="fas fa-check-circle"></i>'; break;
            case 'warning': icon = '<i class="fas fa-exclamation-triangle"></i>'; break;
            case 'error': icon = '<i class="fas fa-times-circle"></i>'; break;
        }
        
        logEntry.innerHTML = `<span class="log-time">[${timestamp}]</span> ${icon} ${message}`;
        logContent.appendChild(logEntry);
        logContent.scrollTop = logContent.scrollHeight;
    }
    
    function delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    // Inicialização
    addLog('info', '🚀 Sistema de sincronização pronto');
    addLog('info', '📊 Selecione um arquivo CSV para começar');
});