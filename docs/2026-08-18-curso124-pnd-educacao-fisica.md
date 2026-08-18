# Curso 124 — PND na prática: EDUCAÇÃO FÍSICA

Data: 2026-08-18
Curso: 124 — "PND na prática - EDUCAÇÃO FÍSICA: preparação para a Prova Nacional Docente"
Modelo: cursos 118 a 123

Sétimo curso preparatório para a Prova Nacional Docente, na habilitação Educação
Física, para quem atua nos **anos finais do fundamental e no ensino médio**.

Escolhido por razão comercial, não técnica: é um público **grande e mal servido**
por preparatórios, então a concorrência é menor que em Pedagogia, Letras,
Matemática ou História.

## O que foi construído

| | |
|---|---|
| Módulos | 17 |
| Aulas (`tipo=html`) | **61** (média de 61.205 caracteres) |
| Banco de questões | **246** — 90 FGD + 150 Educação Física + 6 discursivas |
| Simulado (quiz 30) | 30 + 50 + 1 = **81 por tentativa**, 330 min, 3 tentativas, 60% |
| 3 tentativas | **243 questões distintas, zero reutilização** |
| Turma | 78 · PNDEDF01-2026A, aberta, 17/08 a 31/12/2026 |
| Preço | 150,00 → 100,00, em promoção |

Módulos 08–14: Concepções e currículo · Corpo e desenvolvimento motor ·
Esportes (lógica interna) · Ensino dos esportes · Jogos e lutas · Ginásticas,
danças e aventura · Saúde na escola · Inclusão, avaliação e convivência.

## As travas desta habilitação

O risco aqui é diferente de todas as anteriores: **a área tem um histórico de
exclusão embutido na própria prática**, e um risco de extrapolar para fora da
docência.

1. **Nem recreação nem treino.** Os dois erros clássicos — reduzir a aula ao
   "rola a bola" e reduzi-la ao treinamento desportivo — entram como distrator e
   nunca como gabarito. Prática corporal é objeto cultural;
2. **Não prescrever treino, dieta nem saúde individual.** Proibido série, carga,
   repetições, frequência cardíaca-alvo, dieta, suplemento ou perda de peso, nem
   no gabarito nem como premissa. Forma professor, não personal trainer. Terceira
   aplicação da mesma trava, depois dos cursos 116 e 123;
3. **Padrão corporal e desempenho não são critério** de avaliação nem objetivo de
   aula. Gordofobia, capacitismo e vergonha corporal são objeto de ensino;
4. **Coeducação é objeto de trabalho**, não se evita: nada de "futebol de menino,
   dança de menina";
5. **Segurança sem eliminar o conteúdo.** Caso exemplar: luta na escola não é
   briga, e recusá-la por medo de violência é erro didático, não prudência;
6. **Não inventar dado nem protocolo** — sem percentual de sedentarismo, bateria
   de testes ou tabela de aptidão.

### As travas viraram conteúdo

Medição sobre as 60 primeiras aulas:

| Tema | Aulas |
|---|---|
| Luta × briga | **60 de 60** |
| Gordofobia e padrão corporal | 51 |
| Coeducação e gênero | 50 |
| Aula sem objeto ("rola a bola") | 46 |

A varredura acusou 12 aulas citando série, carga ou frequência cardíaca-alvo — a
trava mais crítica da área. A leitura mostrou **falso alarme do melhor tipo**:
quatro casaram em "suplementar" no sentido de *atendimento educacional
especializado*, e as demais são o material **proibindo** a prescrição:

> "Não prescreva série, carga, número de repetições, frequência cardíaca-alvo,
> dieta, suplemento ou meta de perda de peso — nada disso é atribuição docente."

Uma aula ensina o professor a lidar com o estudante que traz promessa de
emagrecimento da internet: analisar quem divulga e o que a indústria vende, e
indicar que orientação individual é dos serviços de saúde, informando como
acessá-los.

## Segundo curso seguido a passar nas sete checagens

O quiz 30 passou nas sete verificações de `verificar_banco_questoes.php` na
primeira importação, incluindo a de FGD sem disciplina carimbada — criada no dia
anterior, depois de o usuário encontrar "erro recorrente em matemática" na
questão 1 do simulado de História.

## Correções no banco

Dez ao todo. Nove da conferência, com destaque para três:

- **progressão da BNCC errada na explicação**: danças de matriz indígena e
  africana são objeto do 3º ao 5º ano, não do 6º/7º. Como a explicação é exibida
  ao aluno depois do envio, ela ensinava a progressão errada;
- **impossibilidade material**: o enunciado dava "duas bolas em condições de uso"
  e o gabarito propunha quatro campos simultâneos de jogo de invasão. Ajustado
  para quatro bolas;
- **ambiguidade que tornava um distrator defensável**: a proibição de repetir
  movimento valia só dentro de cada bloco, então "8 movimentos" era leitura
  legítima do enunciado. Passou a valer para a música inteira.

Mais "Quatro falas" com cinco alternativas e um "como se caber-lhe" agramatical.

A décima não veio da conferência e é de **forma**: em `jogos-lutas[10]` a
alternativa correta era a única sem oração explicativa — "O registro do Aluno 2."
contra "O registro do Aluno 1, que condiciona a continuidade da luta...". A
assimetria entregava a resposta. As cinco viraram referência pura, e a análise
voltou para onde deveria estar: a leitura dos registros no enunciado.

## A regra de comprimento, na terceira tentativa de acertar

A checagem mostrou **"correta é a mais longa: 0%"** — e isso é obra da regra que
eu mesmo escrevi.

| Curso | Regra usada | "correta é a mais longa" |
|---|---|---|
| 118–122 | correção retroativa, faixa 85–115% | 36% (acaso ≈ 20%) |
| 123 | "pelo menos um distrator maior" | 4% |
| 124 | "pelo menos um maior E pelo menos um menor" | **0%** |

Exigir que pelo menos um distrator seja mais longo **impede por construção** que
a correta seja a máxima. Quem eliminar sempre a mais longa nunca elimina a certa:
chuta em 4 opções, não em 5.

O efeito é pequeno perto do original — antes o aluno acertava ~91% marcando a
mais longa; agora ganha uma eliminação garantida, 25% contra 20% — mas é
sistemático, e é a terceira vez que erro essa regra.

**A regra correta não impõe posição: a correta deve ser a mais longa em cerca de
1 questão a cada 5, como o acaso.** Fica registrado para o próximo curso.

Os 6 casos em que a correta ficou perceptivelmente mais curta são todos do tipo
**"identifique a fala equivocada"**, verificados um a um pelo comando do
enunciado. Ali a resposta é uma frase de aluno e ser curta é autêntico — a
exceção já documentada no curso 123.

## Economia que se confirmou

O aviso incluído no prompt do conferente — de que `embaralhar_alternativas` está
ligado e a ordem gravada não chega ao aluno — funcionou: **nenhum dos dez
conferentes reportou posição de gabarito**, contra três no curso 123.

## Execução

| Workflow | Agentes | Resultado |
|---|---|---|
| 61 aulas | 61 | 61/61. Caiu duas vezes; na segunda, o item 1915 falhou por erro de conexão e voltou no resume |
| 156 questões | 21 | 10 redatores + 10 conferentes + 1 de discursivas, sem falha |

## Pendências

**Thumbnail**: sem imagem, como 121, 122 e 123.

**Revisão por professor de Educação Física antes de divulgar**, com prioridade
para o módulo de saúde — onde a fronteira entre conteúdo escolar e prescrição é
a linha que o curso inteiro se propõe a não cruzar — e para o de inclusão.

A distribuição de temas foi montada por conhecimento da área e pela BNCC, **não
pela matriz oficial de Educação Física da PND**.
