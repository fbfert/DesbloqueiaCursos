# midia-cursos Specification

## Purpose
Manter as capas de cursos e categorias leves para o celular do aluno, sem exigir que a equipe trate as imagens antes de enviá-las e sem perder nenhuma capa existente.

## Requirements

### Requirement: Otimização da capa no upload
Ao receber uma capa de curso ou de categoria válida, o sistema SHALL gravar uma versão com largura máxima de 1280 px, proporção preservada, recodificada em WebP quando houver suporte e em JPEG caso contrário (PNG comprimido se houver transparência e não houver WebP). Imagens menores que o limite MUST NOT ser ampliadas.

#### Scenario: PNG grande de 1672 px
- **WHEN** o administrador envia uma capa PNG de 1672×941 px e 1,9 MB
- **THEN** a capa gravada tem 1280 px de largura e no máximo 400 KB, e o curso passa a apontar para ela

#### Scenario: Imagem pequena
- **WHEN** a capa enviada tem 800 px de largura
- **THEN** a largura gravada continua 800 px

### Requirement: Upload nunca falha por causa da otimização
Se a otimização não for possível (GIF animado, formato sem decodificador, imagem acima de 40 megapixels, erro de memória ou de escrita), o sistema MUST gravar o arquivo original exatamente como o upload atual faz e registrar o motivo no log, sem exibir erro ao administrador.

#### Scenario: GIF animado
- **WHEN** a capa enviada é um GIF animado
- **THEN** o arquivo original é gravado e o curso aponta para ele

#### Scenario: Falha do codificador
- **WHEN** o codificador de imagem falha durante a otimização
- **THEN** o upload termina com sucesso usando o arquivo original e o log registra a falha

### Requirement: Validações atuais preservadas
As validações de upload existentes (tamanho máximo de 5 MB, extensões e tipos MIME permitidos, mensagens de erro) MUST continuar idênticas.

#### Scenario: Arquivo acima de 5 MB
- **WHEN** a capa enviada tem 6 MB
- **THEN** o upload é recusado com a mesma mensagem de hoje

### Requirement: Manutenção das capas existentes
O script de manutenção SHALL listar, sem alterar nada, as capas referenciadas por cursos e categorias que podem ser otimizadas (largura acima de 1280 px ou arquivo acima de 300 KB) e o ganho estimado; com `--aplicar`, SHALL criar as versões otimizadas, atualizar as referências no banco em transação e gravar um manifesto; com `--reverter`, SHALL restaurar as referências originais a partir do manifesto. Os arquivos originais MUST NOT ser apagados nem movidos.

#### Scenario: Simulação
- **WHEN** o script roda sem opções
- **THEN** ele lista as capas candidatas com tamanho atual e estimado, e nenhum arquivo ou registro muda

#### Scenario: Aplicação
- **WHEN** o script roda com `--aplicar`
- **THEN** cada capa candidata ganha uma versão otimizada ao lado da original, o banco passa a apontar para ela e o manifesto lista cada troca

#### Scenario: Reversão
- **WHEN** o script roda com `--reverter` e o manifesto da aplicação
- **THEN** cada curso e categoria volta a apontar para a capa original

#### Scenario: Capa referenciada que não existe no disco
- **WHEN** um curso aponta para uma capa ausente
- **THEN** o script registra o caso no relatório e segue para as demais, sem alterar aquele registro
