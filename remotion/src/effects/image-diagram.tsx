import React from "react";
import { AbsoluteFill, Img, useCurrentFrame, interpolate, spring } from "remotion";
import { COLORS, FONTS, headlineGradient, ENGRAVE_SHADOW } from "../style-tokens";
import type { PrimitiveProps } from "../primitives";

type DiagramNode = { label?: string; src?: string; caption?: string };

const toNodes = (raw: unknown): DiagramNode[] => {
  if (!Array.isArray(raw)) return [];
  return raw.slice(0, 4).map((n): DiagramNode => {
    if (typeof n === "string") return { label: n };
    if (n && typeof n === "object") {
      const o = n as Record<string, unknown>;
      return {
        label: typeof o.label === "string" ? o.label : undefined,
        src: typeof o.src === "string" && o.src.length > 0 ? o.src : undefined,
        caption: typeof o.caption === "string" ? o.caption : undefined,
      };
    }
    return {};
  });
};

const ImageDiagram: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const total = Math.max(1, Math.round((anim.end - anim.start) * fps));
  const params = (anim.params ?? {}) as Record<string, unknown>;

  const parsed = toNodes(params.nodes);
  const items: DiagramNode[] = parsed.length
    ? parsed
    : [{ label: "Etapa 1" }, { label: "Etapa 2" }, { label: "Etapa 3" }];

  const title = typeof anim.text === "string" ? anim.text.trim() : "";
  const stagger =
    typeof params.stagger === "number" && params.stagger > 0
      ? Math.round(params.stagger)
      : Math.max(5, Math.min(14, Math.floor(total / (items.length + 2))));

  const textColor = dark ? COLORS.textOnDark : COLORS.textOnLight;
  const mutedColor = dark ? COLORS.mutedOnDark : COLORS.mutedOnLight;

  const mediaH = items.length >= 4 ? 230 : items.length === 3 ? 300 : 380;
  const gapH = items.length >= 4 ? 52 : 74;

  const outro = interpolate(frame, [total - 8, total], [1, 0], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
  const titleIn = spring({ frame, fps, config: { damping: 200 } });

  return (
    <AbsoluteFill
      style={{
        alignItems: "center",
        justifyContent: "center",
        padding: "0 48px",
        opacity: outro,
      }}
    >
      <div
        style={{
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          width: 800,
        }}
      >
        {title.length > 0 ? (
          <h1
            style={{
              margin: "0 0 44px",
              maxWidth: 780,
              textAlign: "center",
              fontFamily: FONTS.display,
              fontWeight: 400,
              fontSize: 74,
              lineHeight: 0.9,
              textTransform: "uppercase",
              backgroundImage: headlineGradient(),
              backgroundClip: "text",
              WebkitBackgroundClip: "text",
              color: "transparent",
              opacity: titleIn,
              transform: `translateY(${(1 - titleIn) * 26}px)`,
            }}
          >
            {title}
          </h1>
        ) : null}

        {items.map((node, i) => {
          const delay = 6 + i * stagger;
          const enter = spring({
            frame: frame - delay,
            fps,
            config: { damping: 200, mass: 0.7 },
          });
          const link = spring({
            frame: frame - (delay - Math.round(stagger / 2)),
            fps,
            config: { damping: 200, mass: 0.5 },
          });

          return (
            <React.Fragment key={i}>
              {i > 0 ? (
                <div
                  style={{
                    height: gapH,
                    display: "flex",
                    flexDirection: "column",
                    alignItems: "center",
                    justifyContent: "center",
                    transform: `scaleY(${link})`,
                    transformOrigin: "top center",
                    opacity: link,
                  }}
                >
                  <div
                    style={{
                      width: 4,
                      height: gapH - 18,
                      borderRadius: 999,
                      backgroundImage: headlineGradient(),
                    }}
                  />
                  <div
                    style={{
                      width: 0,
                      height: 0,
                      borderLeft: "12px solid transparent",
                      borderRight: "12px solid transparent",
                      borderTop: `16px solid ${COLORS.gold}`,
                    }}
                  />
                </div>
              ) : null}

              <div
                style={{
                  width: 760,
                  boxSizing: "border-box",
                  padding: 16,
                  borderRadius: 16,
                  background: COLORS.vellum,
                  border: `1px solid ${COLORS.leather}`,
                  boxShadow: ENGRAVE_SHADOW,
                  opacity: enter,
                  transform: `translateY(${(1 - enter) * 44}px) scale(${0.94 + enter * 0.06})`,
                }}
              >
                <div
                  style={{
                    width: "100%",
                    height: mediaH,
                    borderRadius: 12,
                    overflow: "hidden",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    backgroundImage: node.src
                      ? undefined
                      : `repeating-linear-gradient(135deg, ${COLORS.ink} 0 18px, ${COLORS.papyrus} 18px 36px)`,
                  }}
                >
                  {node.src ? (
                    <Img
                      src={node.src}
                      style={{
                        width: "100%",
                        height: mediaH,
                        objectFit: "cover",
                        display: "block",
                      }}
                    />
                  ) : (
                    <span
                      style={{
                        fontFamily: FONTS.mono,
                        fontSize: 20,
                        letterSpacing: "0.16em",
                        textTransform: "uppercase",
                        color: mutedColor,
                      }}
                    >
                      {node.label ? node.label : `IMAGE ${i + 1}`}
                    </span>
                  )}
                </div>

                <div
                  style={{
                    display: "flex",
                    alignItems: "center",
                    gap: 14,
                    marginTop: 16,
                  }}
                >
                  <div
                    style={{
                      flex: "0 0 auto",
                      width: 42,
                      height: 42,
                      borderRadius: 999,
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      backgroundImage: headlineGradient(),
                      color: COLORS.papyrus,
                      fontFamily: FONTS.body,
                      fontWeight: 700,
                      fontSize: 20,
                    }}
                  >
                    {i + 1}
                  </div>
                  <div style={{ minWidth: 0 }}>
                    <div
                      style={{
                        fontFamily: FONTS.display,
                        fontSize: 34,
                        lineHeight: 0.98,
                        textTransform: "uppercase",
                        color: textColor,
                      }}
                    >
                      {node.label ? node.label : `Etapa ${i + 1}`}
                    </div>
                    {node.caption ? (
                      <div
                        style={{
                          marginTop: 6,
                          fontFamily: FONTS.body,
                          fontWeight: 400,
                          fontSize: 22,
                          lineHeight: 1.4,
                          color: mutedColor,
                        }}
                      >
                        {node.caption}
                      </div>
                    ) : null}
                  </div>
                </div>
              </div>
            </React.Fragment>
          );
        })}
      </div>
    </AbsoluteFill>
  );
};

export default ImageDiagram;