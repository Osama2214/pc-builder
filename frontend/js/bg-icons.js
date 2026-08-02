const BG_ICON_DEFS = {
  cpu: `<rect x="6" y="6" width="12" height="12" rx="1"></rect><rect x="9" y="9" width="6" height="6" rx="0.5"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="15" x2="4" y2="15"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="15" x2="23" y2="15"></line>`,
  gpu: `<rect x="2" y="7" width="20" height="10" rx="1.5"></rect><circle cx="8" cy="12" r="2.6"></circle><circle cx="16" cy="12" r="2.6"></circle><line x1="4" y1="17" x2="4" y2="19"></line><line x1="7" y1="17" x2="7" y2="19"></line>`,
  ram: `<rect x="3" y="4" width="18" height="8" rx="1"></rect><line x1="6" y1="12" x2="6" y2="15"></line><line x1="9" y1="12" x2="9" y2="15"></line><line x1="12" y1="12" x2="12" y2="15"></line><line x1="15" y1="12" x2="15" y2="15"></line><line x1="18" y1="12" x2="18" y2="15"></line>`,
  fan: `<circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="1.6"></circle><path d="M12 12 C 12 6, 16 5, 17 8 C 18 11, 14 12, 12 12 Z"></path><path d="M12 12 C 6 12, 5 8, 8 7 C 11 6, 12 10, 12 12 Z"></path><path d="M12 12 C 12 18, 8 19, 7 16 C 6 13, 10 12, 12 12 Z"></path><path d="M12 12 C 18 12, 19 16, 16 17 C 13 18, 12 14, 12 12 Z"></path>`,
  case: `<rect x="6" y="2" width="12" height="20" rx="1"></rect><circle cx="12" cy="6" r="1"></circle><line x1="9" y1="10" x2="15" y2="10"></line><line x1="9" y1="13" x2="15" y2="13"></line>`,
  storage: `<rect x="3" y="6" width="18" height="12" rx="1"></rect><circle cx="8" cy="12" r="2.5"></circle><line x1="14" y1="9" x2="18" y2="9"></line><line x1="14" y1="12" x2="18" y2="12"></line><line x1="14" y1="15" x2="18" y2="15"></line>`,
  psu: `<path d="M9 2v5"></path><path d="M15 2v5"></path><path d="M7 7h10v4a5 5 0 0 1-5 5 5 5 0 0 1-5-5V7z"></path><path d="M12 16v6"></path>`,
};

// Curated, not random — keeps the scatter consistent across loads and avoids
// icons overlapping each other or the centered card. Larger, blurred entries
// sit further "back" for a bit of depth; small crisp ones read as foreground.
const BG_ICON_LAYOUT = [
  { icon: "cpu", top: "6%", left: "7%", size: 56, opacity: 0.16, duration: 9, delay: 0 },
  { icon: "case", top: "4%", left: "50%", size: 42, opacity: 0.11, duration: 10, delay: 0.4 },
  { icon: "storage", top: "10%", left: "84%", size: 50, opacity: 0.15, duration: 8.5, delay: 0.8 },
  { icon: "ram", top: "28%", left: "2%", size: 46, opacity: 0.13, duration: 8, delay: 1.4 },
  { icon: "ram", top: "18%", left: "92%", size: 38, opacity: 0.11, duration: 9.5, delay: 0.2 },
  { icon: "gpu", top: "68%", left: "4%", size: 68, opacity: 0.13, duration: 11, delay: 1.2 },
  { icon: "fan", top: "78%", left: "90%", size: 58, opacity: 0.14, duration: 13, delay: 2 },
  { icon: "psu", top: "88%", left: "38%", size: 42, opacity: 0.15, duration: 10.5, delay: 0.9 },
  { icon: "cpu", top: "46%", left: "95%", size: 32, opacity: 0.11, muted: true, duration: 12, delay: 2.4 },
  { icon: "case", top: "42%", left: "1%", size: 44, opacity: 0.1, muted: true, duration: 10, delay: 0.3 },
  { icon: "storage", top: "92%", left: "82%", size: 38, opacity: 0.12, duration: 9, delay: 1.8 },
  { icon: "fan", top: "60%", left: "22%", size: 44, opacity: 0.09, muted: true, duration: 12.5, delay: 1 },
  { icon: "gpu", top: "-4%", left: "88%", size: 100, opacity: 0.05, blur: 3, duration: 15, delay: 0.5 },
  { icon: "fan", top: "60%", left: "70%", size: 96, opacity: 0.05, blur: 3, duration: 16, delay: 1.6 },
  { icon: "cpu", top: "94%", left: "10%", size: 34, opacity: 0.1, duration: 11.5, delay: 2.1 },
  { icon: "psu", top: "50%", left: "48%", size: 28, opacity: 0.06, muted: true, duration: 13.5, delay: 0.6 },
];

// Shorter, sparser layout for the homepage hero — it's a wide-but-short strip
// with a headline/buttons through the middle, so icons stay mostly in the
// margins instead of the same tall scatter used for the auth card pages.
const HERO_BG_ICON_LAYOUT = [
  { icon: "cpu", top: "10%", left: "4%", size: 40, opacity: 0.12, duration: 9, delay: 0 },
  { icon: "gpu", top: "15%", left: "89%", size: 46, opacity: 0.1, duration: 10, delay: 0.5 },
  { icon: "ram", top: "65%", left: "6%", size: 34, opacity: 0.1, duration: 8.5, delay: 1 },
  { icon: "fan", top: "68%", left: "91%", size: 44, opacity: 0.11, muted: true, duration: 11, delay: 1.4 },
  { icon: "storage", top: "8%", left: "24%", size: 30, opacity: 0.08, duration: 9.5, delay: 0.3 },
  { icon: "psu", top: "80%", left: "50%", size: 28, opacity: 0.07, duration: 10.5, delay: 0.8 },
  { icon: "case", top: "40%", left: "2%", size: 36, opacity: 0.07, muted: true, duration: 10, delay: 0.6 },
  { icon: "cpu", top: "38%", left: "96%", size: 30, opacity: 0.08, duration: 12, delay: 1.8 },
  { icon: "gpu", top: "-6%", left: "14%", size: 80, opacity: 0.04, blur: 3, duration: 15, delay: 0.4 },
  { icon: "storage", top: "88%", left: "84%", size: 32, opacity: 0.09, duration: 9, delay: 1.2 },
];

function injectBgIcons(container, layout = BG_ICON_LAYOUT) {
  if (!container || container.querySelector(".bg-icons")) return;

  const wrap = document.createElement("div");
  wrap.className = "bg-icons";
  wrap.setAttribute("aria-hidden", "true");

  wrap.innerHTML = layout.map((item) => {
    const cls = item.muted ? "bg-icon bg-icon-muted" : "bg-icon";
    const filter = item.blur ? ` filter: blur(${item.blur}px);` : "";
    return `
      <svg class="${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
        style="top:${item.top}; left:${item.left}; width:${item.size}px; height:${item.size}px; opacity:${item.opacity}; animation-duration:${item.duration}s; animation-delay:${item.delay}s;${filter}">
        ${BG_ICON_DEFS[item.icon]}
      </svg>
    `;
  }).join("");

  container.prepend(wrap);
}

function renderBgIcons() {
  document.querySelectorAll(".bg-icons-host").forEach((container) => injectBgIcons(container));
}

document.addEventListener("DOMContentLoaded", renderBgIcons);
