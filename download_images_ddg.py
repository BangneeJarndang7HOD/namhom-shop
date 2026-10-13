import os
import urllib.request
from duckduckgo_search import DDGS
import ssl

ssl._create_default_https_context = ssl._create_unverified_context

def download_image(query, filename):
    print(f"Searching for {query}")
    try:
        results = DDGS().images(query, max_results=5)
        for r in results:
            url = r.get("image")
            if url:
                print(f"Downloading {url}")
                try:
                    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
                    with urllib.request.urlopen(req, timeout=10) as response, open(filename, 'wb') as out_file:
                        out_file.write(response.read())
                    return True
                except Exception as dl_e:
                    print(f"Failed to download {url}: {dl_e}")
    except Exception as e:
        print(f"Error {query}: {e}")
    return False

queries = {
    "miss_dior.jpg": "Miss Dior Blooming Bouquet perfume bottle white background",
    "coco_mademoiselle.jpg": "Chanel Coco Mademoiselle perfume bottle isolated",
    "jo_malone.jpg": "Jo Malone Wood Sage Sea Salt perfume bottle isolated",
    "tom_ford.jpg": "Tom Ford Oud Wood perfume bottle isolated",
    "versace_mini.jpg": "Versace Eros blue perfume bottle isolated",
    "marc_jacobs_mini.jpg": "Marc Jacobs Daisy perfume bottle isolated",
    "ysl_libre.jpg": "YSL Libre perfume bottle isolated",
    "acqua_di_gio.jpg": "Giorgio Armani Acqua Di Gio perfume bottle isolated",
    "creed_aventus.jpg": "Creed Aventus perfume bottle isolated",
    "bvlgari_omnia.jpg": "Bvlgari Omnia Crystalline perfume bottle isolated",
    "gucci_bloom.jpg": "Gucci Bloom perfume bottle isolated",
    "lancome_lavieestbelle.jpg": "Lancome La Vie Est Belle perfume bottle isolated"
}

for fname, q in queries.items():
    filepath = os.path.join("uploads/products", fname)
    download_image(q, filepath)
