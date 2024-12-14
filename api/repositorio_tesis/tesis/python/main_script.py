import json
import sys

def run_main(file_path):
    try:
        # Simulated response structure
        response = {
            "success": True,
            "message": "Processing complete",
            "tesis": {
                "id": 0,
                "id_autor": 123,
                "id_coordinacion": 1,
                "id_coordinacion_2": 2,
                "id_grado": 4,
                "filename": file_path,
                "id_opcion_terminal": 3,
                "titulo": "Simulated Thesis Title",
                "fecha": "2024-11-05",
                "palabras_clave": "example, keywords",
                "resumen": "This is a simulated abstract of the thesis."
            }
        }

    except Exception as e:
        response = {
            "success": False,
            "message": str(e)
        }

    # Print the JSON response only
    print(json.dumps(response))

if __name__ == "__main__":
    file_path = sys.argv[1] if len(sys.argv) > 1 else None
    if file_path:
        run_main(file_path)
    else:
        print(json.dumps({"success": False, "message": "No file path provided"}))
