import { Suspense, useMemo } from 'react'
import { Canvas } from '@react-three/fiber'
import { Grid, OrbitControls, PerspectiveCamera } from '@react-three/drei'
import * as THREE from 'three'
import type { FloorPlan, PlacedItem } from '../types'
import { CHAIR, chairPositions, objectHeightFt } from '../lib/layout'

const CHAIR_HEIGHT = 1.6
const WALL_HEIGHT = 8
const deg2rad = (d: number) => (d * Math.PI) / 180

function Chair({ x, z, color }: { x: number; z: number; color: string }) {
  const seat = CHAIR.size * 0.85
  return (
    <group position={[x, 0, z]}>
      {/* seat */}
      <mesh position={[0, CHAIR_HEIGHT * 0.5, 0]} castShadow>
        <boxGeometry args={[seat, CHAIR_HEIGHT * 0.55, seat]} />
        <meshStandardMaterial color={color} />
      </mesh>
      {/* back */}
      <mesh position={[0, CHAIR_HEIGHT * 0.85, -seat * 0.4]} castShadow>
        <boxGeometry args={[seat, CHAIR_HEIGHT * 0.7, seat * 0.18]} />
        <meshStandardMaterial color={color} />
      </mesh>
    </group>
  )
}

function Item({ item, roomW, roomD }: { item: PlacedItem; roomW: number; roomD: number }) {
  const cx = item.x - roomW / 2
  const cz = item.y - roomD / 2
  const height = objectHeightFt(item)
  const chairs = chairPositions(item)
  const chairColor = '#8a94a6'

  return (
    <group position={[cx, 0, cz]} rotation={[0, -deg2rad(item.rotation), 0]}>
      {/* Table / object */}
      {item.shape === 'round' ? (
        <mesh position={[0, height / 2, 0]} castShadow receiveShadow>
          <cylinderGeometry args={[item.widthFt / 2, item.widthFt / 2, height, 40]} />
          <meshStandardMaterial color={item.color} />
        </mesh>
      ) : (
        <mesh position={[0, height / 2, 0]} castShadow receiveShadow>
          <boxGeometry args={[item.widthFt, height, item.depthFt]} />
          <meshStandardMaterial color={item.color} />
        </mesh>
      )}
      {chairs.map((c, i) => (
        <Chair key={i} x={c.x} z={c.y} color={chairColor} />
      ))}
    </group>
  )
}

function Walls({ w, d }: { w: number; d: number }) {
  const t = 0.4
  const mat = <meshStandardMaterial color="#e2e8f0" side={THREE.DoubleSide} transparent opacity={0.55} />
  return (
    <group>
      <mesh position={[0, WALL_HEIGHT / 2, -d / 2]}>
        <boxGeometry args={[w, WALL_HEIGHT, t]} />
        {mat}
      </mesh>
      <mesh position={[0, WALL_HEIGHT / 2, d / 2]}>
        <boxGeometry args={[w, WALL_HEIGHT, t]} />
        {mat}
      </mesh>
      <mesh position={[-w / 2, WALL_HEIGHT / 2, 0]}>
        <boxGeometry args={[t, WALL_HEIGHT, d]} />
        {mat}
      </mesh>
      <mesh position={[w / 2, WALL_HEIGHT / 2, 0]}>
        <boxGeometry args={[t, WALL_HEIGHT, d]} />
        {mat}
      </mesh>
    </group>
  )
}

export default function FloorPlan3D({ plan }: { plan: FloorPlan }) {
  const { roomWidthFt: w, roomDepthFt: d } = plan
  const camPos = useMemo<[number, number, number]>(
    () => [w * 0.7, Math.max(w, d) * 0.9, d * 0.9],
    [w, d],
  )

  return (
    <Canvas shadows dpr={[1, 2]} className="h-full w-full">
      <color attach="background" args={['#0f172a']} />
      <PerspectiveCamera makeDefault position={camPos} fov={50} />
      <OrbitControls makeDefault target={[0, 0, 0]} maxPolarAngle={Math.PI / 2.05} />

      <ambientLight intensity={0.6} />
      <directionalLight
        position={[w, Math.max(w, d) * 1.2, d]}
        intensity={1.1}
        castShadow
        shadow-mapSize={[2048, 2048]}
      />
      <hemisphereLight intensity={0.35} groundColor="#1e293b" />

      <Suspense fallback={null}>
        {/* Floor */}
        <mesh rotation={[-Math.PI / 2, 0, 0]} position={[0, 0, 0]} receiveShadow>
          <planeGeometry args={[w, d]} />
          <meshStandardMaterial color="#f8fafc" />
        </mesh>
        <Grid
          args={[w, d]}
          cellSize={1}
          cellThickness={0.5}
          cellColor="#cbd5e1"
          sectionSize={5}
          sectionThickness={1}
          sectionColor="#94a3b8"
          position={[0, 0.01, 0]}
          fadeDistance={Math.max(w, d) * 3}
          infiniteGrid={false}
        />
        <Walls w={w} d={d} />
        {plan.items.map((item) => (
          <Item key={item.id} item={item} roomW={w} roomD={d} />
        ))}
      </Suspense>
    </Canvas>
  )
}
