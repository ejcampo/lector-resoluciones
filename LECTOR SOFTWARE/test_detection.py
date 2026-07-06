import sys
import os

base_dir = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(base_dir, "app", "Services", "PDF"))

from pdf_converter import find_publiquese_y_fraction
import fitz

pdf_path = os.path.join(base_dir, "storage", "temp", "uploads", "1783351932_6a4bca7c756ab_RS-05286-06-2026.pdf")

doc = fitz.open(pdf_path)
page = doc[-1]

print("Buscando en la ultima pagina...")
search_terms = ["PUBL", "COMUNIQUESE", "CUMPLASE", "PUBLÍQUESE", "COMUNÍQUESE", "CÚMPLASE"]

for term in search_terms:
    instances = page.search_for(term)
    if instances:
        print(f"Encontrado '{term}' en Y={instances[0].y0}")
    else:
        print(f"No encontrado '{term}'")

fraction = find_publiquese_y_fraction(pdf_path, len(doc)-1)
print(f"Fraccion devuelta: {fraction}")
doc.close()
