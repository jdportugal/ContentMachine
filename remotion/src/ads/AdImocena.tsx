import React from "react";
import {
  AbsoluteFill,
  Audio,
  Img,
  OffthreadVideo,
  Sequence,
  interpolate,
  spring,
  staticFile,
  useCurrentFrame,
  useVideoConfig,
} from "remotion";
import { loadThemeFonts } from "../fonts";

/**
 * An Imocena ad, Hormozi-style: Hook (the callout) → Agitate (the old way's cost)
 * → Demo (paste the link, get the reel) → Stack (what's in it) → CTA (first one free).
 * Three variants test one callout each: price, speed, no hassle.
 */

export type Variante = "preco" | "minutos" | "simples";

export type Secao = { segundos: number; audio: string | null; legenda: string };

export type AdProps = {
  variante: Variante;
  secoes: Secao[]; // 5: hook, agitate, demo, stack, cta
  reel: string | null; // the demo reel (public dir)
  fotos: string[]; // demo house photos (public dir)
};

const FPS = 30;
export const duracaoEmFrames = (p: AdProps) =>
  Math.max(30, Math.round(p.secoes.reduce((s, x) => s + x.segundos, 0) * FPS));

const C = {
  navy: "#0E1626",
  navy2: "#141F33",
  navy3: "#1B2638",
  cyan: "#05B8E6",
  coral: "#F06052",
  ivory: "#F4F1EA",
  soft: "#AEB5C1",
};
const DISPLAY = "'Cormorant Garamond', Georgia, serif";
const SANS = "'Poppins', 'Helvetica Neue', Arial, sans-serif";

const src = (f: string) => (/^https?:/.test(f) ? f : staticFile(f));

/** A springy 0→1 starting at frame `de`. */
const useEntrada = (de = 0, amortecimento = 14) => {
  const f = useCurrentFrame();
  const { fps } = useVideoConfig();
  return spring({ frame: f - de, fps, config: { damping: amortecimento, mass: 0.7 } });
};

// ── shared pieces ──────────────────────────────────────────────────────────

const Marca: React.FC = () => (
  <div style={{ position: "absolute", top: 90, left: 0, right: 0, display: "flex", justifyContent: "center", alignItems: "baseline", gap: 14 }}>
    <span style={{ fontFamily: DISPLAY, fontSize: 64, fontWeight: 600, color: C.ivory, letterSpacing: 1 }}>Imocena</span>
    <span style={{ width: 14, height: 14, borderRadius: 7, background: C.cyan, display: "inline-block" }} />
  </div>
);

/** The house photo behind a section, slowly pushing in, darkened. */
const FotoDeFundo: React.FC<{ foto?: string; escurecer?: number }> = ({ foto, escurecer = 0.72 }) => {
  const f = useCurrentFrame();
  if (!foto) return null;
  const s = interpolate(f, [0, 150], [1.08, 1.18], { extrapolateRight: "clamp" });
  return (
    <AbsoluteFill>
      <Img src={src(foto)} style={{ width: "100%", height: "100%", objectFit: "cover", transform: `scale(${s})` }} />
      <AbsoluteFill style={{ background: `linear-gradient(180deg, rgba(14,22,38,${escurecer}) 0%, rgba(14,22,38,${Math.min(1, escurecer + 0.18)}) 60%, ${C.navy} 100%)` }} />
    </AbsoluteFill>
  );
};

/** The voiceover's words, big, the current one in coral (most people watch muted). */
const Legenda: React.FC<{ texto: string; segundos: number }> = ({ texto, segundos }) => {
  const f = useCurrentFrame();
  const palavras = texto.split(/\s+/).filter(Boolean);
  const total = Math.max(1, segundos * FPS - 12);
  const atual = Math.min(palavras.length - 1, Math.floor((f / total) * palavras.length));
  const entrada = interpolate(f, [0, 6], [0, 1], { extrapolateRight: "clamp" });
  return (
    <div style={{ position: "absolute", left: 70, right: 70, bottom: 230, textAlign: "center", opacity: entrada }}>
      <p style={{ margin: 0, fontFamily: SANS, fontWeight: 700, fontSize: 58, lineHeight: 1.22, color: C.ivory, textShadow: "0 4px 24px rgba(0,0,0,.55)" }}>
        {palavras.map((p, i) => (
          <span key={i} style={{ color: i === atual ? C.coral : i < atual ? C.ivory : "rgba(244,241,234,.55)" }}>
            {p}{" "}
          </span>
        ))}
      </p>
    </div>
  );
};

const Etiqueta: React.FC<{ children: React.ReactNode; cor?: string }> = ({ children, cor = C.cyan }) => (
  <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 22 }}>
    <span style={{ width: 70, height: 2, background: cor }} />
    <span style={{ fontFamily: SANS, fontSize: 30, letterSpacing: 9, color: cor, fontWeight: 600 }}>{children}</span>
    <span style={{ width: 70, height: 2, background: cor }} />
  </div>
);

/** An iPhone-ish frame playing the demo reel. */
const Telemovel: React.FC<{ reel: string | null; largura: number; desde?: number; foto?: string }> = ({ reel, largura, desde = 0, foto }) => {
  const altura = Math.round(largura * 2.08);
  return (
    <div style={{ width: largura, height: altura, borderRadius: largura * 0.14, background: "#05070C", padding: largura * 0.035, boxShadow: "0 40px 120px rgba(0,0,0,.6), 0 0 0 3px #2A3446" }}>
      <div style={{ width: "100%", height: "100%", borderRadius: largura * 0.11, overflow: "hidden", position: "relative", background: C.navy }}>
        {reel ? (
          <OffthreadVideo src={src(reel)} startFrom={Math.round(desde * FPS)} muted style={{ width: "100%", height: "100%", objectFit: "cover" }} />
        ) : foto ? (
          <Img src={src(foto)} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
        ) : null}
        <div style={{ position: "absolute", top: largura * 0.03, left: "50%", transform: "translateX(-50%)", width: largura * 0.3, height: largura * 0.075, borderRadius: 40, background: "#05070C" }} />
      </div>
    </div>
  );
};

const Demonstracao: React.FC = () => (
  <span style={{ fontFamily: SANS, fontSize: 22, color: C.soft, letterSpacing: 2 }}>IMÓVEL DE DEMONSTRAÇÃO</span>
);

// ── 1 · Hook ───────────────────────────────────────────────────────────────

const Hook: React.FC<{ variante: Variante; fotos: string[] }> = ({ variante, fotos }) => {
  const a = useEntrada(2, 10);
  const b = useEntrada(14);
  const f = useCurrentFrame();
  return (
    <AbsoluteFill>
      <FotoDeFundo foto={fotos[0]} escurecer={0.6} />
      <Marca />
      <AbsoluteFill style={{ alignItems: "center", justifyContent: "center", paddingBottom: 380 }}>
        {variante === "preco" && (
          <div style={{ textAlign: "center" }}>
            <Etiqueta>CONSULTOR IMOBILIÁRIO?</Etiqueta>
            <div style={{ marginTop: 30, fontFamily: DISPLAY, fontWeight: 700, fontSize: 330, lineHeight: 0.9, color: C.coral, transform: `scale(${0.6 + 0.4 * a})`, opacity: a }}>10 €</div>
            <div style={{ marginTop: 18, fontFamily: SANS, fontWeight: 600, fontSize: 52, letterSpacing: 14, color: C.ivory, opacity: b }}>POR VÍDEO</div>
          </div>
        )}
        {variante === "minutos" && (() => {
          const seg = Math.min(299, Math.floor(interpolate(f, [6, 60], [0, 299], { extrapolateLeft: "clamp", extrapolateRight: "clamp" })));
          const mm = String(Math.floor(seg / 60)).padStart(2, "0");
          const ss = String(seg % 60).padStart(2, "0");
          const arco = interpolate(f, [6, 60], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
          return (
            <div style={{ textAlign: "center" }}>
              <Etiqueta>DO LINK AO VÍDEO</Etiqueta>
              <div style={{ position: "relative", width: 560, height: 560, margin: "40px auto 0", opacity: a, transform: `scale(${0.7 + 0.3 * a})` }}>
                <svg width={560} height={560} style={{ position: "absolute", inset: 0, transform: "rotate(-90deg)" }}>
                  <circle cx={280} cy={280} r={250} stroke="rgba(244,241,234,.15)" strokeWidth={22} fill="none" />
                  <circle cx={280} cy={280} r={250} stroke={C.cyan} strokeWidth={22} fill="none" strokeLinecap="round" strokeDasharray={2 * Math.PI * 250} strokeDashoffset={2 * Math.PI * 250 * (1 - arco)} />
                </svg>
                <AbsoluteFill style={{ alignItems: "center", justifyContent: "center" }}>
                  <span style={{ fontFamily: SANS, fontWeight: 700, fontSize: 150, color: C.ivory, fontVariantNumeric: "tabular-nums" }}>{mm}:{ss}</span>
                </AbsoluteFill>
              </div>
              <div style={{ marginTop: 30, fontFamily: DISPLAY, fontWeight: 700, fontSize: 130, color: C.coral, opacity: b }}>em minutos</div>
            </div>
          );
        })()}
        {variante === "simples" && (
          <div style={{ textAlign: "left" }}>
            {["Sem filmar.", "Sem editar.", "Sem complicações."].map((t, i) => {
              const e = spring({ frame: f - (4 + i * 14), fps: FPS, config: { damping: 14, mass: 0.7 } });
              return (
                <div key={t} style={{ fontFamily: DISPLAY, fontWeight: 700, fontSize: i === 2 ? 128 : 116, lineHeight: 1.12, color: i === 2 ? C.coral : C.ivory, opacity: e, transform: `translateX(${(1 - e) * -80}px)` }}>{t}</div>
              );
            })}
          </div>
        )}
      </AbsoluteFill>
    </AbsoluteFill>
  );
};

// ── 2 · Agitate: the old way ───────────────────────────────────────────────

const Riscado: React.FC<{ de: number; children: React.ReactNode }> = ({ de, children }) => {
  const f = useCurrentFrame();
  const r = interpolate(f, [de, de + 10], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  return (
    <span style={{ position: "relative", display: "inline-block", color: r > 0.5 ? C.soft : C.ivory }}>
      {children}
      <span style={{ position: "absolute", left: -6, top: "54%", height: 8, borderRadius: 4, background: C.coral, width: `calc(${r * 100}% + 12px)` }} />
    </span>
  );
};

const Linha: React.FC<{ de: number; icone: string; corIcone: string; children: React.ReactNode }> = ({ de, icone, corIcone, children }) => {
  const e = useEntrada(de);
  return (
    <div style={{ display: "flex", alignItems: "center", gap: 34, opacity: e, transform: `translateY(${(1 - e) * 30}px)` }}>
      <span style={{ width: 92, height: 92, borderRadius: 46, background: corIcone, color: C.navy, fontFamily: SANS, fontWeight: 800, fontSize: 56, display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}>{icone}</span>
      <span style={{ fontFamily: SANS, fontWeight: 600, fontSize: 58, color: C.ivory }}>{children}</span>
    </div>
  );
};

const Agitar: React.FC<{ variante: Variante; fotos: string[] }> = ({ variante, fotos }) => {
  const t = useEntrada(0);
  return (
    <AbsoluteFill>
      <FotoDeFundo foto={fotos[1]} escurecer={0.82} />
      <Marca />
      <AbsoluteFill style={{ alignItems: "center", justifyContent: "center", paddingBottom: 420 }}>
        <div style={{ opacity: t }}>
          <Etiqueta cor={C.coral}>{variante === "simples" ? "NÃO PRECISA DE" : "À MODA ANTIGA"}</Etiqueta>
        </div>
        <div style={{ marginTop: 60, display: "flex", flexDirection: "column", gap: 44 }}>
          {variante === "preco" && (
            <>
              <Linha de={6} icone="✕" corIcone={C.coral}><Riscado de={30}>Videógrafo: centenas de €</Riscado></Linha>
              <Linha de={16} icone="✕" corIcone={C.coral}><Riscado de={40}>Dias à espera do vídeo</Riscado></Linha>
              <Linha de={26} icone="✕" corIcone={C.coral}><Riscado de={50}>Marcar visita para filmar</Riscado></Linha>
            </>
          )}
          {variante === "minutos" && (
            <>
              <Linha de={6} icone="✕" corIcone={C.coral}><Riscado de={30}>3 a 5 dias de espera</Riscado></Linha>
              <Linha de={16} icone="✕" corIcone={C.coral}><Riscado de={40}>Uma tarde a editar</Riscado></Linha>
              <Linha de={26} icone="✕" corIcone={C.coral}><Riscado de={50}>O imóvel já vendeu</Riscado></Linha>
            </>
          )}
          {variante === "simples" && (
            <>
              <Linha de={6} icone="✕" corIcone={C.coral}><Riscado de={30}>Câmara ou drone</Riscado></Linha>
              <Linha de={16} icone="✕" corIcone={C.coral}><Riscado de={40}>Programas de edição</Riscado></Linha>
              <Linha de={26} icone="✕" corIcone={C.coral}><Riscado de={50}>Gravar a sua voz</Riscado></Linha>
            </>
          )}
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};

// ── 3 · Demo: paste the link, get the reel ─────────────────────────────────

const Demo: React.FC<{ reel: string | null; fotos: string[] }> = ({ reel, fotos }) => {
  const f = useCurrentFrame();
  const link = "remax.pt/imoveis/moradia-t3-lajeosa";
  const letras = Math.floor(interpolate(f, [4, 34], [0, link.length], { extrapolateLeft: "clamp", extrapolateRight: "clamp" }));
  const tel = useEntrada(30, 13);
  return (
    <AbsoluteFill style={{ background: C.navy }}>
      <Marca />
      <div style={{ position: "absolute", top: 220, left: 80, right: 80 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 18, height: 104, borderRadius: 52, background: C.ivory, padding: "0 40px", boxShadow: "0 20px 60px rgba(0,0,0,.35)" }}>
          <span style={{ fontFamily: SANS, fontSize: 36, color: "#7C8696" }}>🔗</span>
          <span style={{ fontFamily: SANS, fontSize: 38, color: C.navy, whiteSpace: "nowrap", overflow: "hidden" }}>
            {link.slice(0, letras)}
            <span style={{ opacity: f % 20 < 10 ? 1 : 0, color: C.coral }}>|</span>
          </span>
        </div>
        <div style={{ textAlign: "center", marginTop: 26, fontFamily: SANS, fontSize: 32, color: C.cyan, letterSpacing: 4, opacity: interpolate(f, [30, 40], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" }) }}>↓ VÍDEO PRONTO</div>
      </div>
      <div style={{ position: "absolute", top: 450, left: 0, right: 0, display: "flex", flexDirection: "column", alignItems: "center", gap: 18, opacity: tel, transform: `translateY(${(1 - tel) * 120}px) scale(${0.85 + 0.15 * tel})` }}>
        <Telemovel reel={reel} largura={400} desde={2} foto={fotos[0]} />
        <Demonstracao />
      </div>
    </AbsoluteFill>
  );
};

// ── 4 · Stack: what's in it ────────────────────────────────────────────────

const Stack: React.FC<{ variante: Variante; reel: string | null; fotos: string[] }> = ({ variante, reel, fotos }) => {
  const f = useCurrentFrame();
  const itens =
    variante === "minutos"
      ? ["Narração em português", "Fotos com movimento", "O seu contacto no fim", "Pronto para Reels e TikTok"]
      : variante === "simples"
        ? ["Narração profissional", "Música incluída", "As melhores fotos", "O seu contacto no fim"]
        : ["Narração profissional", "Fotos com movimento", "O seu contacto no fim", "Edite e refaça grátis"];
  return (
    <AbsoluteFill style={{ background: C.navy }}>
      <Marca />
      <div style={{ position: "absolute", top: 250, left: 70, display: "flex", flexDirection: "column", alignItems: "center", gap: 14 }}>
        <Telemovel reel={reel} largura={330} desde={14} foto={fotos[2]} />
        <Demonstracao />
      </div>
      <div style={{ position: "absolute", top: 330, left: 470, right: 50, display: "flex", flexDirection: "column", gap: 40 }}>
        {itens.map((t, i) => {
          const e = spring({ frame: f - (4 + i * 9), fps: FPS, config: { damping: 14, mass: 0.7 } });
          return (
            <div key={t} style={{ display: "flex", alignItems: "center", gap: 22, opacity: e, transform: `translateX(${(1 - e) * 60}px)` }}>
              <span style={{ width: 64, height: 64, borderRadius: 32, background: C.cyan, color: C.navy, fontFamily: SANS, fontWeight: 800, fontSize: 38, display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}>✓</span>
              <span style={{ fontFamily: SANS, fontWeight: 600, fontSize: 42, lineHeight: 1.15, color: C.ivory }}>{t}</span>
            </div>
          );
        })}
      </div>
    </AbsoluteFill>
  );
};

// ── 5 · CTA: first one free ────────────────────────────────────────────────

const CTA: React.FC<{ variante: Variante; fotos: string[] }> = ({ variante, fotos }) => {
  const a = useEntrada(2, 11);
  const b = useEntrada(12);
  const f = useCurrentFrame();
  const pulso = 1 + 0.04 * Math.sin(f / 5);
  const selo = variante === "preco" ? "Depois, desde 10 € por vídeo" : variante === "minutos" ? "Pronto em minutos" : "Só precisa do link";
  return (
    <AbsoluteFill>
      <FotoDeFundo foto={fotos[3] ?? fotos[0]} escurecer={0.78} />
      <Marca />
      <AbsoluteFill style={{ alignItems: "center", justifyContent: "center", paddingBottom: 420, textAlign: "center" }}>
        <div style={{ fontFamily: SANS, fontWeight: 600, fontSize: 40, letterSpacing: 10, color: C.cyan, opacity: a }}>EXPERIMENTE</div>
        <div style={{ marginTop: 10, fontFamily: DISPLAY, fontWeight: 700, fontSize: 168, lineHeight: 0.95, color: C.ivory, opacity: a, transform: `scale(${0.8 + 0.2 * a})` }}>
          1.º vídeo<br /><span style={{ color: C.coral }}>grátis</span>
        </div>
        <div style={{ marginTop: 54, padding: "30px 70px", borderRadius: 80, background: C.coral, fontFamily: SANS, fontWeight: 700, fontSize: 60, color: C.navy, opacity: b, transform: `scale(${(0.8 + 0.2 * b) * pulso})` }}>imocena.com</div>
        <div style={{ marginTop: 34, fontFamily: SANS, fontSize: 36, color: C.ivory, opacity: b }}>{selo}</div>
      </AbsoluteFill>
      <div style={{ position: "absolute", bottom: 80, left: 60, right: 60, textAlign: "center", fontFamily: SANS, fontSize: 22, lineHeight: 1.4, color: C.soft }}>
        10 € por vídeo no pack de 10 (100 €, IVA incl.). Vídeo grátis com marca d’água. Imóvel de demonstração.
      </div>
    </AbsoluteFill>
  );
};

// ── the ad ─────────────────────────────────────────────────────────────────

export const AdImocena: React.FC<AdProps> = ({ variante, secoes, reel, fotos }) => {
  loadThemeFonts("Cormorant Garamond", "Poppins");
  const blocos = [
    <Hook variante={variante} fotos={fotos} />,
    <Agitar variante={variante} fotos={fotos} />,
    <Demo reel={reel} fotos={fotos} />,
    <Stack variante={variante} reel={reel} fotos={fotos} />,
    <CTA variante={variante} fotos={fotos} />,
  ];
  let inicio = 0;
  return (
    <AbsoluteFill style={{ background: C.navy }}>
      {secoes.map((s, i) => {
        const de = Math.round(inicio * FPS);
        const dur = Math.max(1, Math.round(s.segundos * FPS));
        inicio += s.segundos;
        return (
          <Sequence key={i} from={de} durationInFrames={dur} name={`secao-${i + 1}`}>
            {blocos[i]}
            {i !== 4 && <Legenda texto={s.legenda} segundos={s.segundos} />}
            {s.audio && <Audio src={src(s.audio)} />}
          </Sequence>
        );
      })}
    </AbsoluteFill>
  );
};
