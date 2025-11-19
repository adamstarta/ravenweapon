// Product Database
const products = [
  {
    id: "5-56-raven",
    name: "Lockhart Tactical .223 RAVEN",
    category: "Waffe",
    basePrice: 2985,
    description: "Die .223 RAVEN ist eine hochpräzise Waffe für professionelle Anwendungen. Entwickelt nach höchsten Qualitätsstandards und gefertigt in Kanada, bietet diese Waffe zuverlässige Leistung in jeder Situation.",
    available: true,
    variantType: "image", // "image" for weapons, "color" for caliber kits
    variants: [
      {
        color: "Graphite Black",
        colorCode: "#2C2C2C",
        image: "assets/5.56 RAVEN.png",
        thumbnail: "assets/5.56 RAVEN.png", // When you add color-specific images, update this
        priceModifier: 0
      },
      {
        color: "Flat Dark Earth",
        colorCode: "#C9B896",
        image: "assets/5.56 RAVEN.png", // Replace with FDE version when available
        thumbnail: "assets/5.56 RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Northern Lights",
        colorCode: "#4A7C8C",
        image: "assets/5.56 RAVEN.png", // Replace with Northern Lights version when available
        thumbnail: "assets/5.56 RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Olive Drab Green",
        colorCode: "#6B7C4B",
        image: "assets/5.56 RAVEN.png", // Replace with OD Green version when available
        thumbnail: "assets/5.56 RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Sniper Grey",
        colorCode: "#7A7F84",
        image: "assets/5.56 RAVEN.png", // Replace with Sniper Grey version when available
        thumbnail: "assets/5.56 RAVEN.png",
        priceModifier: 0
      }
    ],
    specs: {
      caliber: "5.56x45mm NATO / .223 Rem",
      manufacturer: "Lockhart Tactical"
    }
  },
  {
    id: "300-aac-raven",
    name: "Lockhart Tactical 300 AAC RAVEN",
    category: "Waffe",
    basePrice: 2985,
    description: "Die 300 AAC RAVEN kombiniert Präzision mit Vielseitigkeit. Ideal für verschiedene Einsatzbereiche und optimiert für maximale Zuverlässigkeit.",
    available: true,
    variantType: "image",
    variants: [
      {
        color: "Graphite Black",
        colorCode: "#2C2C2C",
        image: "assets/300 AAC RAVEN.png",
        thumbnail: "assets/300 AAC RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Flat Dark Earth",
        colorCode: "#C9B896",
        image: "assets/300 AAC RAVEN.png",
        thumbnail: "assets/300 AAC RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Northern Lights",
        colorCode: "#4A7C8C",
        image: "assets/300 AAC RAVEN.png",
        thumbnail: "assets/300 AAC RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Olive Drab Green",
        colorCode: "#6B7C4B",
        image: "assets/300 AAC RAVEN.png",
        thumbnail: "assets/300 AAC RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Sniper Grey",
        colorCode: "#7A7F84",
        image: "assets/300 AAC RAVEN.png",
        thumbnail: "assets/300 AAC RAVEN.png",
        priceModifier: 0
      }
    ],
    specs: {
      caliber: "300 AAC Blackout",
      manufacturer: "Lockhart Tactical"
    }
  },
  {
    id: "7-62x39-raven",
    name: "Lockhart Tactical 7.62×39 RAVEN",
    category: "Waffe",
    basePrice: 2985,
    description: "Die 7.62×39 RAVEN bietet robuste Leistung und bewährte Zuverlässigkeit. Perfekt für anspruchsvolle Einsätze.",
    available: true,
    variantType: "image",
    variants: [
      {
        color: "Graphite Black",
        colorCode: "#2C2C2C",
        image: "assets/7.62×39 RAVEN.png",
        thumbnail: "assets/7.62×39 RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Flat Dark Earth",
        colorCode: "#C9B896",
        image: "assets/7.62×39 RAVEN.png",
        thumbnail: "assets/7.62×39 RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Northern Lights",
        colorCode: "#4A7C8C",
        image: "assets/7.62×39 RAVEN.png",
        thumbnail: "assets/7.62×39 RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Olive Drab Green",
        colorCode: "#6B7C4B",
        image: "assets/7.62×39 RAVEN.png",
        thumbnail: "assets/7.62×39 RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Sniper Grey",
        colorCode: "#7A7F84",
        image: "assets/7.62×39 RAVEN.png",
        thumbnail: "assets/7.62×39 RAVEN.png",
        priceModifier: 0
      }
    ],
    specs: {
      caliber: "7.62×39mm",
      manufacturer: "Lockhart Tactical"
    }
  },
  {
    id: "9mm-raven",
    name: "Lockhart Tactical 9mm RAVEN",
    category: "Waffe",
    basePrice: 2985,
    description: "Die 9mm RAVEN ist die perfekte Wahl für präzise Schüsse auf kurze bis mittlere Distanzen. Entwickelt für höchste Zuverlässigkeit.",
    available: true,
    variantType: "image",
    variants: [
      {
        color: "Graphite Black",
        colorCode: "#2C2C2C",
        image: "assets/9mm RAVEN.png",
        thumbnail: "assets/9mm RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Flat Dark Earth",
        colorCode: "#C9B896",
        image: "assets/9mm RAVEN.png",
        thumbnail: "assets/9mm RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Northern Lights",
        colorCode: "#4A7C8C",
        image: "assets/9mm RAVEN.png",
        thumbnail: "assets/9mm RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Olive Drab Green",
        colorCode: "#6B7C4B",
        image: "assets/9mm RAVEN.png",
        thumbnail: "assets/9mm RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Sniper Grey",
        colorCode: "#7A7F84",
        image: "assets/9mm RAVEN.png",
        thumbnail: "assets/9mm RAVEN.png",
        priceModifier: 0
      }
    ],
    specs: {
      caliber: "9mm Luger",
      manufacturer: "Lockhart Tactical"
    }
  },
  {
    id: "22lr-raven",
    name: "Lockhart Tactical .22 RAVEN",
    category: "Waffe",
    basePrice: 2985,
    description: "Die .22 RAVEN ist ideal für Präzisionsschießen und Training. Zuverlässige Leistung mit geringem Rückstoß für höchste Genauigkeit.",
    available: true,
    variantType: "image",
    variants: [
      {
        color: "Graphite Black",
        colorCode: "#2C2C2C",
        image: "assets/.22 RAVEN.png",
        thumbnail: "assets/.22 RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Flat Dark Earth",
        colorCode: "#C9B896",
        image: "assets/.22 RAVEN.png",
        thumbnail: "assets/.22 RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Northern Lights",
        colorCode: "#4A7C8C",
        image: "assets/.22 RAVEN.png",
        thumbnail: "assets/.22 RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Olive Drab Green",
        colorCode: "#6B7C4B",
        image: "assets/.22 RAVEN.png",
        thumbnail: "assets/.22 RAVEN.png",
        priceModifier: 0
      },
      {
        color: "Sniper Grey",
        colorCode: "#7A7F84",
        image: "assets/.22 RAVEN.png",
        thumbnail: "assets/.22 RAVEN.png",
        priceModifier: 0
      }
    ],
    specs: {
      caliber: ".22 Long Rifle",
      manufacturer: "Lockhart Tactical"
    }
  },
  {
    id: "9mm-caliber-kit",
    name: "Lockhart Tactical 9mm CALIBER KIT",
    category: "Zubehör",
    basePrice: 1685,
    description: "Das 9mm Kaliber Kit ermöglicht die einfache Umrüstung Ihrer RAVEN Waffe. Enthält alle notwendigen Komponenten für einen schnellen Kaliberwechsel.",
    available: true,
    variantType: "color",
    variants: [
      {
        color: "Standard",
        colorCode: "#2C2C2C",
        image: "assets/9mm CALIBER KIT.png",
        priceModifier: 0
      }
    ],
    specs: {
      caliber: "9mm Luger",
      manufacturer: "Lockhart Tactical",
      type: "Conversion Kit"
    }
  },
  {
    id: "300-aac-caliber-kit",
    name: "Lockhart Tactical 300 AAC CALIBER KIT",
    category: "Zubehör",
    basePrice: 1685,
    description: "Das 300 AAC Kaliber Kit für die flexible Nutzung verschiedener Kaliber. Hochwertige Verarbeitung und einfache Installation.",
    available: true,
    variantType: "color",
    variants: [
      {
        color: "Standard",
        colorCode: "#2C2C2C",
        image: "assets/300 AAC CALIBER KIT.png",
        priceModifier: 0
      }
    ],
    specs: {
      caliber: "300 AAC Blackout",
      manufacturer: "Lockhart Tactical",
      type: "Conversion Kit"
    }
  },
  {
    id: "7-62x39-caliber-kit",
    name: "Lockhart Tactical 7.62×39 CALIBER KIT",
    category: "Zubehör",
    basePrice: 1685,
    description: "Das 7.62×39 Kaliber Kit bietet maximale Flexibilität für Ihre RAVEN Plattform. Professionelle Qualität für zuverlässige Leistung.",
    available: true,
    variantType: "color",
    variants: [
      {
        color: "Standard",
        colorCode: "#2C2C2C",
        image: "assets/7.62X39 CALIBER KIT.png",
        priceModifier: 0
      }
    ],
    specs: {
      caliber: "7.62×39mm",
      manufacturer: "Lockhart Tactical",
      type: "Conversion Kit"
    }
  }
];

// Helper function to get product by ID
function getProductById(id) {
  return products.find(product => product.id === id);
}
