const BUILD_SLOTS = [
  { key: "cpu", label: "CPU", multi: false, required: true },
  { key: "motherboard", label: "Motherboard", multi: false, required: true },
  { key: "ram", label: "Memory (RAM)", multi: true, required: true },
  { key: "storage", label: "Storage", multi: true, required: true },
  { key: "psu", label: "Power Supply", multi: false, required: true },
  { key: "gpu", label: "Graphics Card", multi: false, required: false },
  { key: "cooler", label: "CPU Cooler", multi: false, required: false },
  { key: "case", label: "Case", multi: false, required: false },
];

function capitalize(value) {
  if (!value) return "";
  return value.charAt(0).toUpperCase() + value.slice(1);
}
