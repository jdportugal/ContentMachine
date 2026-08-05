# Capital Próprio — Sistema de Design

**Publicação sobre dinheiro. Leitor de 30–60 anos.**
Semanário impresso + web + newsletter de quarta-feira. Português europeu, formal.
Base visual: **Pergaminho**, lido como serigrafia de meados do século — tinta plana, traço grosso, meio-tom visível, registo deslocado.

> Versão 2.0 · verde dominante · ficheiro de origem: `Capital Proprio Design System v2.dc.html`
> (a v1, mais sóbria, mantém-se em `Capital Proprio Design System.dc.html`)

---

## 1. Cor

Cada cor é uma **chapa de impressão**, não um degradé. Zero gradientes, zero sombras desfocadas.

| Tinta | Hex | Função |
|---|---|---|
| Verde | `#2F5D50` | Campo da página, secção *Pessoal*, sinal de subida, dinheiro |
| Preto | `#1A1410` | Traço (bordas 3px), barras de secção, sombra dura |
| Terracota | `#B4451F` | Segunda tinta: secção *Mercados*, sinal de descida |
| Ouro | `#C8941E` | Destaque: linha ★, citações, botões, texto sobre preto |
| Papel | `#F4EDE2` | Superfície de leitura e texto sobre tintas planas |
| Violeta | `#6B5B8A` | Secção *Negócio* (uso contido) |

### Regras
- **Uma cor de secção por página.** Nunca duas.
- **Uma única superfície preta por página** (barra de totais, número da semana, rodapé).
- O ouro só se usa como **texto sobre preto**. Sobre verde ou terracota, o texto é **papel** (`#F4EDE2`) — o ouro sobre verde mede 2,75:1 e reprova.
- Fundo da página: verde plano + meio-tom de 7px em `rgba(26,20,16,0.16)`.
- Sem imagens de textura, sem padrões decorativos. A matéria vem do meio-tom e da trama a 45°.

### Secções editoriais
| Secção | Cor | Conteúdo |
|---|---|---|
| Pessoal | verde | Poupança, orçamento, crédito, reforma |
| Mercados | terracota | Bolsa, taxas, obrigações, câmbios |
| Economia | ouro | Política, impostos, inflação, salários |
| Negócio | violeta | Empresas, imobiliário, perfis |

### Sinal financeiro
`▲ subida` em verde · `▼ descida` em terracota · `— estável` em papel.
A seta acompanha sempre a cor — quem imprime a preto e branco continua a ler o sinal.

---

## 2. Tipografia

| Papel | Família | Pesos |
|---|---|---|
| Títulos e números | **Fraunces** | 900 (caixa alta) · 400 itálico para leads e citações |
| Corpo e interface | **Spline Sans** | 400 · 600 · 700 |
| Metadados, tabelas, etiquetas | **Spline Sans Mono** | 400 · 500 · 600 |

### Escala
| Uso | Tamanho / peso | Notas |
|---|---|---|
| Manchete | 64 / 900 | caixa alta, `letter-spacing -0.045em`, `line-height 0.86` |
| Título de artigo | 40 / 900 | caixa alta, `-0.035em` |
| Lead | 26 / 400 itálico | `line-height 1.3` |
| Intertítulo | 24 / 900 | caixa alta |
| Corpo | 18 / 400 | `line-height 1.6`, medida máxima 68 caracteres, sem justificação |
| Sobrancelha | 12 / 600 mono | caixa alta, `letter-spacing 0.30em` |
| Metadados | 11–12 mono | caixa alta, `letter-spacing 0.08–0.20em` |

**Registo deslocado:** manchetes e grandes números podem levar `text-shadow: 5–6px 5–6px 0` numa segunda tinta. Sempre dura, sempre no mesmo sentido (baixo-direita). Nunca desfocada, nunca cinzenta.

---

## 3. Números

O algarismo é o elemento gráfico principal. Fraunces 900, sempre `font-variant-numeric: tabular-nums`.

| Formato | Exemplo |
|---|---|
| Valor exato | `12 480,50 €` |
| Grandeza | `1,2 M€` |
| Taxa | `2,41 %` |
| Variação de taxa | `+38 p.b.` |

- Espaço fino nos milhares: `1 200` — nunca `1.200`.
- Percentagem com espaço antes do sinal: `3,5 %`.
- Sinal menos verdadeiro `−`, não hífen.
- Cêntimos só quando o cêntimo importa.
- Em tabelas: alinhado à direita, tabular.
- O peso 900 é reservado a números e manchetes. Um número em 600 lê-se como texto e perde a função de imagem.

---

## 4. Grelha, espaço e matéria

- **Grelha de 12 colunas**, goteira 24px. Artigo = 8 col; barra lateral = 4 col.
- **Escala de espaço:** 4 (etiquetas) · 8 (metadados) · 16 (parágrafos) · 24 (blocos) · 56 (painéis) · 96 (secções).
- **Traço:** 3px preto em painéis e separadores fortes; 2px em etiquetas e barras de gráfico.
- **Sombra:** dura, `Xpx Xpx 0 #1A1410` — 14px em painéis de secção, 8px em cartões, 4–6px em botões e badges. Nunca desfocada.
- **Cantos retos.** Sem raio, exceto quando herdado de componentes Pergaminho.
- **Matéria:** meio-tom de 6px (`radial-gradient` de pontos), trama a 45° para conteúdo patrocinado, filete listado preto/papel para separadores fortes.
- **Padding de página:** `clamp(1rem, 3.5vw, 3rem)`; painéis: `clamp(1.4rem, 3.5vw, 2.4rem)`.

---

## 5. Componentes editoriais

A confiança é um componente, não uma nota de rodapé.

| Componente | Regra |
|---|---|
| **Sobrancelha** | mono, caixa alta, cor da secção. Abre sempre a página |
| **Título** | Fraunces 900 caixa alta |
| **Lead** | Fraunces 400 itálico, máx. 46ch |
| **Assinatura** | inicial em quadrado de tinta + nome + credencial (ex. «CFP®, 11 anos de banca») |
| **Metadados** | data · data de atualização · minutos de leitura · «Verificado» |
| **Citação** | painel ouro, Fraunces itálico, atribuição em mono |
| **Faça hoje** | painel verde, texto papel, lista numerada de 3 passos acionáveis |
| **Metodologia** | painel de papel: amostra, pressupostos, fonte, data |
| **Divulgação** | painel de traço tracejado: comissões, conflitos, «informação, não recomendação» |
| **Etiquetas** | secção em tinta plana; tipo de peça (Explicador / Análise / Opinião / Nível) em contorno |
| **Glossário** | termo técnico com sublinhado a ouro de 3px + definição ao passar o cursor |
| **Patrocinado** | trama a 45°, sem serifa 900, sem cor de secção, sem sombra. Nunca imita o editorial |
| **Newsletter CTA** | painel preto, botão ouro, promessa de dia e hora |

---

## 6. Dados e gráficos

- **Uma série por gráfico.** Sem eixo duplo, sem 3D, sem legenda flutuante.
- Linha de 5px em tinta plana, com sombra de registo opcional numa segunda tinta; ponto final marcado com contorno preto e valor rotulado.
- Eixos: base sólida de 3px, guias tracejadas de 1,5px. Sem grelha de fundo.
- Barras com contorno de 2px sobre papel; a categoria «restante» leva meio-tom em vez de tinta.
- **Fonte e método vivem dentro do próprio gráfico ou tabela**, separados por filete de 3px.
- Tabelas: cabeçalho preto, linhas separadas por 3px, números à direita e tabulares.
- A linha `★` leva tinta ouro e é reservada à opção em destaque — uma por tabela.
- Cartões de estatística: número Fraunces 900 na cor do que mede; o cartão de total é preto com número em ouro.

---

## 7. Modelos

| Modelo | Notas |
|---|---|
| **Primeira página** (imprimível) | Cabeça com registo deslocado, filete de 6px, barra de secções, manchete + lead + imagem 3:2, duas chamadas secundárias, coluna «Semana em números» + «Número da semana» em preto |
| **Artigo longo** (web) | 8+4 colunas: sobrancelha → título → lead → assinatura entre filetes; citação em ouro; «Faça hoje» em verde; lateral com «Em resumo», número-chave, patrocinado e fontes |
| **Breve de mercado** | Cartão de papel: hora, título curto, dois deltas em badge, «2 min · Redação» |
| **Newsletter** (600px) | Cabeça verde, três itens numerados em Fraunces, botão preto com sombra ouro, rodapé de subscrição |
| **Cartão móvel** | Etiqueta de secção, título, três valores comparados, botão de leitura. Alvos de toque ≥ 48px |

---

## 8. Voz

Autoridade sem soberba. Primeiro o número, depois a consequência.

**Assim sim**
- «A prestação sobe 23 € por mês.»
- Tratamento formal: *o leitor*, *a subscritora*. Nunca *tu*.
- Percentagem sempre acompanhada do valor em euros.
- Cada afirmação com fonte e data.
- Frases de uma ideia, verbos no presente.

**Assim não**
- «Aproveite esta oportunidade única!»
- Urgência, exclamações, promessas de retorno.
- Anglicismos evitáveis: *yield*, *bearish*, *hedge*.
- Emoji, gradientes, sombras desfocadas.
- Duas cores de secção na mesma página.

**Legibilidade 30–60**
- Corpo a 18px, entrelinha 1,6, medida de 68 caracteres.
- Contraste mínimo 7:1 sobre papel.
- Cinzentos só em metadados, nunca no corpo.
- A cor nunca é o único sinal — `▲ ▼` acompanham sempre.
- Tudo imprime a preto e branco sem perder sentido.

---

## 9. Composição

1. Sobrancelha → título → lead → assinatura. Sempre nesta ordem.
2. Uma cor de secção por página.
3. Uma única superfície preta por página.
4. O `★` só na linha em destaque de uma tabela.
5. Sombra dura sempre no mesmo sentido (baixo-direita).
6. Ouro apenas sobre preto; sobre verde e terracota, texto em papel.

---

## 10. Ajustes disponíveis no ficheiro

| Ajuste | Efeito |
|---|---|
| `showSpecs` | Mostra/esconde as notas de especificação em ouro |
| `sectionTheme` | Cor de secção das maquetas: Pessoal · Mercados · Economia · Negócio |
| `inkOffset` | Deslocamento do registo tipográfico, 0–14px |
