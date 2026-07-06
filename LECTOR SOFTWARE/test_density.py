import os
from PIL import Image

base_dir = os.path.dirname(os.path.abspath(__file__))
pdf_dir = os.path.join(base_dir, "storage", "temp", "images")

def find_signature_block(img_name):
    img_path = os.path.join(pdf_dir, img_name)
    if not os.path.exists(img_path):
        print(f"Not found: {img_path}")
        return

    img = Image.open(img_path).convert('L')
    orig_width, orig_height = img.size
    
    # Resize for faster processing
    scale = 400 / orig_width
    new_height = int(orig_height * scale)
    img = img.resize((400, new_height))
    width, height = img.size

    threshold = 200
    row_density = []
    for y in range(height):
        dark_pixels = sum(1 for x in range(width) if img.getpixel((x, y)) < threshold)
        row_density.append(dark_pixels)

    smoothed = []
    window = 5
    for i in range(height):
        start = max(0, i - window)
        end = min(height, i + window + 1)
        smoothed.append(sum(row_density[start:end]) / (end - start))

    min_density = 3
    gap_size = 4

    blocks = []
    current_block_start = None
    blank_count = 0

    for y in range(height):
        if smoothed[y] >= min_density:
            if current_block_start is None:
                current_block_start = y
            blank_count = 0
        else:
            blank_count += 1
            if blank_count >= gap_size and current_block_start is not None:
                blocks.append((current_block_start, y - gap_size))
                current_block_start = None

    if current_block_start is not None:
        blocks.append((current_block_start, height - 1))

    print(f"\n--- {img_name} ---")
    print(f"Total height: {height} (orig: {orig_height})")
    
    valid_blocks = [b for b in blocks if (b[1]-b[0]) > 20] # filter noise
    for i, b in enumerate(valid_blocks):
        print(f"Valid Block {i}: Y={b[0]} to {b[1]} (height: {b[1]-b[0]})")
        
    if len(valid_blocks) >= 1:
        # The signature block should be the LAST valid block.
        # However, sometimes there's noise at the bottom. 
        # But we filtered noise with height > 20.
        sig_block = valid_blocks[-1]
        
        # We start the crop slightly above the signature block
        crop_start_y = max(0, sig_block[0] - 10)
        
        fraction = crop_start_y / height
        print(f"--> Best crop fraction: {fraction:.3f}")
    
    img.close()

find_signature_block("1783006959_6a4686efe6ac3_1783001289_6a4670c9b79c3_RS-05285-06-2026_pagina_7.jpg")
find_signature_block("1783001695_6a46725feefee_RS-05286-06-2026_pagina_7.jpg")

