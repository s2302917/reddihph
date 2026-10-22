import 'package:flutter/material.dart';

import '../../core/theme.dart';
import '../../services/open_street_map_api.dart';
import '../widgets/open_street_map.dart';

class ResidentStatusMapScreen extends StatelessWidget {
  const ResidentStatusMapScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Colors.white,
        body: Stack(
          fit: StackFit.expand,
          children: [
            const OpenStreetMapView(
              center: OpenStreetMapApi.resident,
              zoom: 15,
              markers: [
                OpenStreetMapMarker(
                  point: OpenStreetMapApi.resident,
                  label: 'Emergency location',
                  color: kSos,
                  icon: Icons.emergency,
                ),
                OpenStreetMapMarker(
                  point: OpenStreetMapApi.hospital,
                  label: 'District Hospital',
                  color: kResidentBrand,
                  icon: Icons.local_hospital,
                ),
              ],
            ),
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: SafeArea(
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                  child: Material(
                    color: Colors.white,
                    elevation: 4,
                    shadowColor: Colors.black26,
                    borderRadius: BorderRadius.circular(16),
                    child: SizedBox(
                      height: 56,
                      child: Row(
                        children: [
                          IconButton(
                            tooltip: 'Close full-screen map',
                            onPressed: () => Navigator.maybePop(context),
                            icon: const Icon(Icons.close, color: kResidentBrand),
                          ),
                          const Expanded(
                            child: Text(
                              'Emergency location',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          const Padding(
                            padding: EdgeInsets.only(right: 16),
                            child: Icon(Icons.my_location,
                                color: kResidentBrand),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      );
}
